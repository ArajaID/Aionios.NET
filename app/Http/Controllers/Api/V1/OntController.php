<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignOntRequest;
use App\Http\Requests\Api\V1\ReturnOntRequest;
use App\Http\Resources\Api\V1\OntResource;
use App\Models\Customer;
use App\Models\Ont;
use App\Models\OntHistory;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags Inventori ONT (ONT Inventory)
 */
class OntController extends Controller
{
    /**
     * Daftar Inventori Modem ONT
     *
     * Menampilkan katalog perangkat modem ONT di gudang maupun yang terpasang di pelanggan, dengan pencarian (ONT ID, nomor seri, merk, tipe, MAC address), filter status (available, installed, returned, damaged, lost), dan paginasi data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:available,installed,returned,damaged,lost'],
            'sort' => ['nullable', 'in:created_at,-created_at,ont_id,-ont_id,status,-status'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Ont::with('currentCustomer');
        if ($search = ($validated['search'] ?? null)) {
            $query->where(function ($builder) use ($search) {
                $builder->where('ont_id', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('mac_address', 'like', "%{$search}%");
            });
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? '-created_at';
        $paginator = $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->paginate($validated['per_page'] ?? 20);

        return ApiResponse::paginated($paginator, OntResource::collection($paginator->getCollection())->resolve());
    }

    /**
     * Detail Modem ONT
     *
     * Menampilkan data spesifikasi lengkap modem ONT, status ketersediaan, serta informasi pelanggan yang sedang menggunakan perangkat tersebut (jika berstatus installed).
     *
     * @param Ont $ont
     * @return JsonResponse
     */
    public function show(Ont $ont): JsonResponse
    {
        return ApiResponse::success((new OntResource($ont->load('currentCustomer')))->resolve());
    }

    /**
     * Riwayat Mutasi / Penggunaan Modem ONT
     *
     * Menampilkan log riwayat perpindahan perangkat ONT: kapan dipasang ke pelanggan, ditarik kembali, diperbaiki, atau ditandai rusak beserta identitas teknisi/petugas yang mencatat.
     *
     * @param Ont $ont
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Ont $ont, Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $history = $ont->histories()->with(['customer:id,customer_id,name', 'admin:id,name'])->paginate($perPage);
        $data = $history->getCollection()->map(fn (OntHistory $item) => [
            'id' => $item->id,
            'action' => $item->action,
            'condition' => $item->condition,
            'notes' => $item->notes,
            'customer' => $item->customer ? [
                'id' => $item->customer->id,
                'customer_id' => $item->customer->customer_id,
                'name' => $item->customer->name,
            ] : null,
            'admin' => $item->admin ? ['id' => $item->admin->id, 'name' => $item->admin->name] : null,
            'created_at' => $item->created_at?->toISOString(),
        ])->all();

        return ApiResponse::paginated($history, $data);
    }

    /**
     * Pasang Modem ONT ke Pelanggan
     *
     * Mengalokasikan perangkat modem ONT berstatus 'available' ke pelanggan tertentu. Status ONT otomatis berubah menjadi 'installed' dan riwayat pemasangan dicatat ke log mutasi.
     *
     * @param AssignOntRequest $request
     * @param Customer $customer
     * @return JsonResponse
     */
    public function assign(AssignOntRequest $request, Customer $customer): JsonResponse
    {
        try {
            $ont = DB::transaction(function () use ($request, $customer) {
                $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                $ont = Ont::whereKey($request->integer('ont_id'))->lockForUpdate()->firstOrFail();
                if ($customer->ont_id !== null) {
                    throw new \DomainException('CUSTOMER_ALREADY_HAS_ONT');
                }
                if ($ont->status !== 'available' || $ont->current_customer_id !== null) {
                    throw new \DomainException('ONT_NOT_AVAILABLE');
                }

                $ont->update([
                    'status' => 'installed',
                    'current_customer_id' => $customer->id,
                    'installed_at' => now(),
                ]);
                $customer->update(['ont_id' => $ont->id]);
                OntHistory::create([
                    'ont_id' => $ont->id,
                    'customer_id' => $customer->id,
                    'action' => 'assigned',
                    'condition' => $ont->condition,
                    'admin_id' => $request->user()->id,
                    'notes' => $request->input('notes') ?: 'ONT assigned through mobile API.',
                ]);
                AuditService::log('assign_ont', 'ont', 'Ont', $ont->id, null, ['customer_id' => $customer->id]);

                return $ont;
            });
        } catch (\DomainException $e) {
            return ApiResponse::error(
                $e->getMessage() === 'ONT_NOT_AVAILABLE' ? 'ONT is not available.' : 'Customer already has an ONT.',
                $e->getMessage(),
                409,
            );
        }

        return ApiResponse::success((new OntResource($ont->load('currentCustomer')))->resolve(), 'ONT assigned successfully.');
    }

    /**
     * Tarik / Kembalikan Modem ONT dari Pelanggan
     *
     * Melepaskan keterikatan modem ONT dari pelanggan dan mengembalikannya ke inventori dengan status baru (available, returned, damaged, atau lost).
     *
     * @param ReturnOntRequest $request
     * @param Customer $customer
     * @return JsonResponse
     */
    public function return(ReturnOntRequest $request, Customer $customer): JsonResponse
    {
        if ($customer->ont_id === null) {
            return ApiResponse::error('Customer does not have an assigned ONT.', 'CUSTOMER_HAS_NO_ONT', 409);
        }

        $ont = DB::transaction(function () use ($request, $customer) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $ont = Ont::whereKey($customer->ont_id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();

            $customer->update(['ont_id' => null]);
            $ont->update([
                'status' => $data['status'],
                'condition' => $data['condition'],
                'current_customer_id' => null,
                'notes' => $data['notes'] ?? $ont->notes,
            ]);
            OntHistory::create([
                'ont_id' => $ont->id,
                'customer_id' => $customer->id,
                'action' => 'returned',
                'condition' => $data['condition'],
                'admin_id' => $request->user()->id,
                'notes' => $data['notes'] ?? 'ONT returned through mobile API.',
            ]);
            AuditService::log('return_ont', 'ont', 'Ont', $ont->id, ['customer_id' => $customer->id], ['status' => $data['status']]);

            return $ont;
        });

        return ApiResponse::success((new OntResource($ont->load('currentCustomer')))->resolve(), 'ONT returned successfully.');
    }

    /**
     * Usulan Nomor ID Registrasi ONT Baru
     *
     * Menghasilkan usulan format ID registrasi ONT baru berikutnya yang unik (contoh: ONT-00012) untuk memudahkan proses input perangkat baru ke sistem inventori.
     *
     * @return JsonResponse
     */
    public function suggestedId(): JsonResponse
    {
        $existingOntIds = Ont::pluck('ont_id')->toArray();
        $maxNum = 0;
        foreach ($existingOntIds as $oid) {
            if (preg_match('/^ONT-(\d+)$/', $oid, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        $nextNum = $maxNum + 1;
        $suggestedId = 'ONT-' . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);

        return ApiResponse::success(['suggested_ont_id' => $suggestedId]);
    }

    /**
     * Tambah Perangkat Modem ONT Baru
     *
     * Mendaftarkan hardware modem/ONT baru ke database inventori gudang dengan status awal 'available'.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ont_id' => 'required|string|max:50|unique:onts,ont_id',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'serial_number' => 'nullable|string|max:100|unique:onts,serial_number',
            'mac_address' => 'nullable|string|max:50',
            'condition' => 'required|in:good,damaged,refurbished',
            'notes' => 'nullable|string|max:500',
        ]);

        $ont = Ont::create([
            ...$validated,
            'status' => 'available',
        ]);

        AuditService::log('create_ont', 'onts', 'Ont', $ont->id, null, $ont->toArray());

        return ApiResponse::success((new OntResource($ont))->resolve(), 'ONT baru berhasil diregistrasi.', 201);
    }
}
