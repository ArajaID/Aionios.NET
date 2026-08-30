<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ActivateCustomerRequest;
use App\Http\Requests\Api\V1\CustomerLifecycleRequest;
use App\Http\Requests\Api\V1\ReactivateCustomerRequest;
use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\NetworkJobResource;
use App\Models\Customer;
use App\Models\CustomerStatusHistory;
use App\Models\Invoice;
use App\Models\Ont;
use App\Models\OntHistory;
use App\Models\Package;
use App\Models\PackageChangeRequest;
use App\Models\PppAccount;
use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\NetworkQueueService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @tags Pelanggan (Customers)
 */
class CustomerController extends Controller
{
    /**
     * Daftar Pelanggan
     *
     * Menampilkan daftar seluruh pelanggan internet dengan kemampuan pencarian (nama, ID pelanggan, telepon, username PPP), filter status (pending, active, isolated, terminated), filter paket, sorting, dan paginasi data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,active,isolated,terminated'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
            'sort' => ['nullable', 'in:created_at,-created_at,customer_id,-customer_id,name,-name,status,-status'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Customer::with(['package', 'ont', 'pppAccount']);
        if ($search = ($validated['search'] ?? null)) {
            $query->where(function ($builder) use ($search) {
                $builder->where('customer_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('pppAccount', fn ($ppp) => $ppp->where('username', 'like', "%{$search}%"));
            });
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['package_id'])) {
            $query->where('package_id', $validated['package_id']);
        }

        [$sort, $direction] = $this->sort($validated['sort'] ?? '-created_at');
        $paginator = $query->orderBy($sort, $direction)->paginate($validated['per_page'] ?? 20);

        return ApiResponse::paginated($paginator, CustomerResource::collection($paginator->getCollection())->resolve());
    }

    /**
     * Registrasi Pelanggan Baru
     *
     * Mendaftarkan calon pelanggan baru ke dalam sistem dengan status awal 'pending'. Pelanggan yang didaftarkan belum memiliki perangkat modem ONT dan belum aktif di router MikroTik sampai proses aktivasi instalasi dilakukan.
     *
     * @param StoreCustomerRequest $request
     * @return JsonResponse
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $customer = Customer::create([
            'customer_id' => $data['customer_id'] ?? 'AIO-'.Str::upper(Str::random(10)),
            'name' => $data['name'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'package_id' => $data['package_id'],
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        CustomerStatusHistory::create([
            'customer_id' => $customer->id,
            'old_status' => 'new',
            'new_status' => 'pending',
            'reason' => 'Customer registered through mobile API.',
            'changed_by' => $request->user()->id,
        ]);
        AuditService::log('create_customer', 'customers', 'Customer', $customer->id, null, $customer->toArray());

        return ApiResponse::success(
            (new CustomerResource($customer->load(['package', 'ont', 'pppAccount'])))->resolve(),
            'Customer created successfully.',
            201,
        );
    }

    /**
     * Detail Profil Pelanggan
     *
     * Menampilkan profil lengkap pelanggan mencakup identitas, alamat, koordinat, paket internet aktif, modem ONT yang terpasang, dan akun PPPoE router.
     *
     * @param Customer $customer
     * @return JsonResponse
     */
    public function show(Customer $customer): JsonResponse
    {
        return ApiResponse::success((new CustomerResource($customer->load(['package', 'ont', 'pppAccount'])))->resolve());
    }

    /**
     * Perbarui Data Pelanggan
     *
     * Memperbarui data profil pelanggan seperti nama, nomor telepon, alamat pemasangan, koordinat lokasi, nomor identitas KTP, atau catatan teknis.
     *
     * @param UpdateCustomerRequest $request
     * @param Customer $customer
     * @return JsonResponse
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $old = $customer->only(['name', 'phone', 'address', 'notes']);
        $customer->update($request->validated());
        AuditService::log('update_customer', 'customers', 'Customer', $customer->id, $old, $customer->only(array_keys($old)));

        return ApiResponse::success(
            (new CustomerResource($customer->load(['package', 'ont', 'pppAccount'])))->resolve(),
            'Customer updated successfully.',
        );
    }

    /**
     * Aktivasi Instalasi Pelanggan Baru
     *
     * Menyelesaikan proses instalasi dan mengaktifkan pelanggan:
     * 1. Menetapkan hardware modem ONT dari gudang ke pelanggan.
     * 2. Membuat kredensial PPPoE dan menjadwalkan job sinkronisasi ke router MikroTik.
     * 3. Menerbitkan tagihan invoice perdana (prorata atau full).
     * 4. Mengubah status pelanggan menjadi 'active'.
     *
     * @param ActivateCustomerRequest $request
     * @param Customer $customer
     * @param BillingService $billingService
     * @param NetworkQueueService $networkQueue
     * @return JsonResponse
     */
    public function activate(
        ActivateCustomerRequest $request,
        Customer $customer,
        BillingService $billingService,
        NetworkQueueService $networkQueue
    ): JsonResponse {
        if ($customer->status !== 'pending') {
            return ApiResponse::error('Only pending customers can be activated.', 'CUSTOMER_STATE_CONFLICT', 409);
        }

        try {
            $result = DB::transaction(function () use ($request, $customer, $billingService, $networkQueue) {
                $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                if ($customer->status !== 'pending' || $customer->pppAccount()->exists()) {
                    throw new \DomainException('CUSTOMER_STATE_CONFLICT');
                }

                $data = $request->validated();
                $package = Package::whereKey($data['package_id'])->where('is_active', true)->firstOrFail();
                $ont = null;
                if (! empty($data['ont_id'])) {
                    $ont = Ont::whereKey($data['ont_id'])->lockForUpdate()->firstOrFail();
                    if ($ont->status !== 'available' || $ont->current_customer_id !== null) {
                        throw new \DomainException('ONT_NOT_AVAILABLE');
                    }
                }

                $activationDate = Carbon::parse($data['activation_date']);
                $customer->update([
                    'package_id' => $package->id,
                    'ont_id' => $ont?->id,
                    'installed_at' => $activationDate,
                    'activated_at' => $activationDate,
                    'status' => 'active',
                ]);

                if ($ont) {
                    $ont->update(['status' => 'installed', 'current_customer_id' => $customer->id, 'installed_at' => $activationDate]);
                    OntHistory::create([
                        'ont_id' => $ont->id,
                        'customer_id' => $customer->id,
                        'action' => 'assigned',
                        'condition' => $ont->condition,
                        'admin_id' => $request->user()->id,
                        'notes' => 'Initial assignment through mobile API.',
                    ]);
                }

                $ppp = PppAccount::create([
                    'customer_id' => $customer->id,
                    'username' => $data['pppoe_username'],
                    'password' => $data['pppoe_password'],
                    'profile' => $package->ppp_profile,
                    'is_active' => true,
                    'status' => 'disconnected',
                ]);
                $job = $networkQueue->queueCreateSecret($ppp->load('customer.package'));
                $billingService->calculateProrataFirstInvoice($customer->load('package'), $activationDate);

                CustomerStatusHistory::create([
                    'customer_id' => $customer->id,
                    'old_status' => 'pending',
                    'new_status' => 'active',
                    'reason' => 'Initial activation through mobile API.',
                    'changed_by' => $request->user()->id,
                ]);
                AuditService::log('activate_customer', 'customers', 'Customer', $customer->id, ['status' => 'pending'], ['status' => 'active', 'network_job_id' => $job->id]);

                return ['customer' => $customer, 'job' => $job];
            });
        } catch (\DomainException $e) {
            $code = $e->getMessage();

            return ApiResponse::error(
                $code === 'ONT_NOT_AVAILABLE' ? 'ONT is not available.' : 'Customer state changed before activation.',
                $code,
                409,
            );
        }

        return ApiResponse::success([
            'customer_status' => 'active',
            'customer' => (new CustomerResource($result['customer']->load(['package', 'ont', 'pppAccount'])))->resolve(),
            'network' => (new NetworkJobResource($result['job']))->resolve(),
        ], 'Customer activated; network synchronization queued.', 202);
    }

    /**
     * Terminasi / Berhenti Berlangganan
     *
     * Memutus layanan pelanggan secara permanen:
     * 1. Mengubah status pelanggan menjadi 'terminated'.
     * 2. Menjadwalkan penonaktifan/penghapusan akun PPPoE di MikroTik RouterOS.
     * 3. Menarik modem ONT kembali ke gudang (status 'returned') jika perangkat ditarik.
     * 4. Membatalkan tagihan belum berjalan yang belum lunas.
     *
     * @param CustomerLifecycleRequest $request
     * @param Customer $customer
     * @param NetworkQueueService $networkQueue
     * @return JsonResponse
     */
    public function terminate(
        CustomerLifecycleRequest $request,
        Customer $customer,
        NetworkQueueService $networkQueue
    ): JsonResponse {
        if (! in_array($customer->status, ['active', 'isolated'], true)) {
            return ApiResponse::error('Customer cannot be terminated from its current state.', 'CUSTOMER_STATE_CONFLICT', 409);
        }

        $result = DB::transaction(function () use ($request, $customer, $networkQueue) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $customer->status;
            $ont = $customer->ont()->lockForUpdate()->first();
            if ($ont) {
                $ont->update(['status' => 'returned', 'current_customer_id' => null]);
                $customer->update(['ont_id' => null]);
                OntHistory::create([
                    'ont_id' => $ont->id,
                    'customer_id' => $customer->id,
                    'action' => 'returned',
                    'condition' => $ont->condition,
                    'admin_id' => $request->user()->id,
                    'notes' => $request->string('reason')->toString(),
                ]);
            }

            $job = $networkQueue->queueCustomer($customer, 'terminate');
            CustomerStatusHistory::create([
                'customer_id' => $customer->id,
                'old_status' => $oldStatus,
                'new_status' => 'terminated',
                'reason' => $request->string('reason')->toString(),
                'changed_by' => $request->user()->id,
            ]);
            AuditService::log('terminate_customer', 'customers', 'Customer', $customer->id, ['status' => $oldStatus], ['status' => 'terminated', 'network_job_id' => $job->id]);

            return ['customer' => $customer, 'job' => $job];
        });

        return ApiResponse::success([
            'customer' => (new CustomerResource($result['customer']->load(['package', 'ont', 'pppAccount'])))->resolve(),
            'network' => (new NetworkJobResource($result['job']))->resolve(),
        ], 'Customer terminated; network synchronization queued.', 202);
    }

    /**
     * Reaktivasi Pelanggan Terminasi
     *
     * Mengaktifkan kembali pelanggan yang sebelumnya telah diputus/terminasi:
     * 1. Memvalidasi tidak ada sisa tunggakan tagihan lama.
     * 2. Menetapkan paket baru dan modem ONT baru jika belum terpasang.
     * 3. Mengaktifkan kembali profil PPPoE di router MikroTik.
     * 4. Menerbitkan invoice baru dan mengubah status pelanggan kembali menjadi 'active'.
     *
     * @param ReactivateCustomerRequest $request
     * @param Customer $customer
     * @param BillingService $billingService
     * @param NetworkQueueService $networkQueue
     * @return JsonResponse
     */
    public function reactivate(
        ReactivateCustomerRequest $request,
        Customer $customer,
        BillingService $billingService,
        NetworkQueueService $networkQueue
    ): JsonResponse {
        if ($customer->status !== 'terminated') {
            return ApiResponse::error('Only terminated customers can be reactivated.', 'CUSTOMER_STATE_CONFLICT', 409);
        }
        if ($customer->outstanding_amount > 0) {
            return ApiResponse::error('Customer still has outstanding balance.', 'CUSTOMER_HAS_OUTSTANDING', 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $customer, $billingService, $networkQueue) {
                $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                $outstanding = Invoice::where('customer_id', $customer->id)
                    ->whereIn('status', ['unpaid', 'overdue'])->lockForUpdate()->sum('total_amount');
                if ((float) $outstanding > 0) {
                    throw new \DomainException('CUSTOMER_HAS_OUTSTANDING');
                }

                $data = $request->validated();
                $package = Package::whereKey($data['package_id'])->where('is_active', true)->firstOrFail();
                $ont = null;
                if (! empty($data['ont_id'])) {
                    $ont = Ont::whereKey($data['ont_id'])->lockForUpdate()->firstOrFail();
                    if ($ont->status !== 'available' || $ont->current_customer_id !== null) {
                        throw new \DomainException('ONT_NOT_AVAILABLE');
                    }
                }

                $activationDate = Carbon::parse($data['activation_date']);
                $customer->update([
                    'status' => 'active',
                    'package_id' => $package->id,
                    'ont_id' => $ont?->id,
                    'activated_at' => $activationDate,
                    'notes' => $data['notes'] ?? $customer->notes,
                ]);
                if ($ont) {
                    $ont->update(['status' => 'installed', 'current_customer_id' => $customer->id, 'installed_at' => $activationDate]);
                    OntHistory::create([
                        'ont_id' => $ont->id,
                        'customer_id' => $customer->id,
                        'action' => 'assigned',
                        'condition' => $ont->condition,
                        'admin_id' => $request->user()->id,
                        'notes' => 'Assigned during reactivation.',
                    ]);
                }

                $ppp = $customer->pppAccount()->lockForUpdate()->firstOrFail();
                if (! empty($data['pppoe_password'])) {
                    $ppp->update(['password' => $data['pppoe_password']]);
                }
                $job = $networkQueue->queueCustomer($customer->fresh(), 'reactivate');
                if (! Invoice::where('customer_id', $customer->id)->where('period', $activationDate->format('Y-m'))->exists()) {
                    $billingService->calculateProrataFirstInvoice($customer->load('package'), $activationDate);
                }

                CustomerStatusHistory::create([
                    'customer_id' => $customer->id,
                    'old_status' => 'terminated',
                    'new_status' => 'active',
                    'reason' => 'Reactivation through mobile API.',
                    'changed_by' => $request->user()->id,
                ]);
                AuditService::log('reactivate_customer', 'customers', 'Customer', $customer->id, ['status' => 'terminated'], ['status' => 'active', 'network_job_id' => $job->id]);

                return ['customer' => $customer, 'job' => $job];
            });
        } catch (\DomainException $e) {
            return ApiResponse::error(
                $e->getMessage() === 'ONT_NOT_AVAILABLE' ? 'ONT is not available.' : 'Customer still has outstanding balance.',
                $e->getMessage(),
                $e->getMessage() === 'ONT_NOT_AVAILABLE' ? 409 : 422,
            );
        }

        return ApiResponse::success([
            'customer' => (new CustomerResource($result['customer']->load(['package', 'ont', 'pppAccount'])))->resolve(),
            'network' => (new NetworkJobResource($result['job']))->resolve(),
        ], 'Customer reactivated; network synchronization queued.', 202);
    }

    /**
     * Perubahan Paket Layanan Internet
     *
     * Memperbarui paket internet pelanggan:
     * - Upgrade (paket setara/lebih tinggi): Langsung diproses dan antrean update bandwidth di MikroTik dijadwalkan seketika.
     * - Downgrade (paket lebih rendah):
     *   - Jika diajukan Owner: Langsung diterapkan.
     *   - Jika diajukan Staff: Masuk ke antrean pengajuan (PackageChangeRequest) untuk menunggu persetujuan Owner.
     *
     * @param Request $request
     * @param Customer $customer
     * @param NetworkQueueService $networkQueue
     * @return JsonResponse
     */
    public function changePackage(
        Request $request,
        Customer $customer,
        NetworkQueueService $networkQueue
    ): JsonResponse {
        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $newPackage = Package::findOrFail($validated['package_id']);

        if ((int) $customer->package_id === (int) $newPackage->id) {
            return ApiResponse::error('Pelanggan sudah menggunakan paket ini.', 'SAME_PACKAGE', 422);
        }

        if ($user && $user->isOwner()) {
            $oldValues = $customer->toArray();
            $customer->update(['package_id' => $newPackage->id]);

            $job = null;
            if ($customer->pppAccount && $customer->status === 'active') {
                $job = $networkQueue->enqueue($customer, 'sync', ['reason' => 'Package changed via mobile API']);
            }

            AuditService::log('change_customer_package', 'customers', 'Customer', $customer->id, $oldValues, $customer->fresh()->toArray());

            return ApiResponse::success([
                'customer' => (new CustomerResource($customer->fresh()->load(['package', 'ont', 'pppAccount'])))->resolve(),
                'network' => $job ? (new NetworkJobResource($job))->resolve() : null,
                'status' => 'applied_directly',
            ], "Paket pelanggan berhasil diubah ke {$newPackage->name} dan disinkronkan.");
        }

        // Staff: create approval request
        $pkgRequest = PackageChangeRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $user->id,
            'old_package_id' => $customer->package_id,
            'new_package_id' => $newPackage->id,
            'reason' => $validated['reason'] ?? 'Pengajuan ganti paket via mobile API',
            'status' => 'pending',
        ]);

        AuditService::log('request_package_change', 'customers', 'PackageChangeRequest', $pkgRequest->id, null, $pkgRequest->toArray());

        return ApiResponse::success([
            'change_request' => $pkgRequest->fresh()->load(['oldPackage', 'newPackage']),
            'status' => 'approval_pending',
        ], 'Pengajuan perubahan paket berhasil dikirim ke Owner untuk disetujui.', 202);
    }

    private function sort(string $sort): array
    {
        return [ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc'];
    }
}
