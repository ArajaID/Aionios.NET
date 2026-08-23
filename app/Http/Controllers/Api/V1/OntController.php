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

class OntController extends Controller
{
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

    public function show(Ont $ont): JsonResponse
    {
        return ApiResponse::success((new OntResource($ont->load('currentCustomer')))->resolve());
    }

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
}
