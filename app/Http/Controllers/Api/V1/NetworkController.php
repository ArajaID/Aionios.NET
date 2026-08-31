<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PppoeSessionQueryRequest;
use App\Http\Resources\Api\V1\NetworkJobResource;
use App\Http\Resources\Api\V1\PppoeSessionResource;
use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\NetworkJob;
use App\Services\AuditService;
use App\Services\NetworkQueueService;
use App\Services\PppoeSessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;


/**
 * @tags Jaringan & MikroTik (Network)
 */
class NetworkController extends Controller
{
    /**
     * Status Koneksi Router MikroTik & Antrean
     *
     * Menampilkan status konektivitas router MikroTik utama (online/offline/unconfigured), waktu sinkronisasi terakhir, dan ringkasan antrean perintah jaringan (pending, processing, failed).
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $router = MikrotikRouter::where('is_active', true)->first();

        return ApiResponse::success([
            'status' => $router?->status ?? 'unconfigured',
            'router' => $router ? [
                'id' => $router->id,
                'name' => $router->name,
                'last_connected_at' => $router->last_connected_at?->toISOString(),
            ] : null,
            'jobs' => [
                'pending' => NetworkJob::where('status', 'pending')->count(),
                'processing' => NetworkJob::where('status', 'processing')->count(),
                'failed' => NetworkJob::where('status', 'failed')->count(),
            ],
        ]);
    }

    /**
     * Daftar Antrean Perintah Jaringan
     *
     * Menampilkan riwayat antrean perintah jaringan (PPPoE secret, isolate, unisolate, update profile) dengan filter status (pending, processing, success, failed), jenis perintah, dan paginasi data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function jobs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,processing,success,failed'],
            'command' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = NetworkJob::query();
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['command'])) {
            $query->where('command', $validated['command']);
        }
        $paginator = $query->latest()->paginate($validated['per_page'] ?? 20);

        return ApiResponse::paginated($paginator, NetworkJobResource::collection($paginator->getCollection())->resolve());
    }

    /**
     * Detail Antrean Perintah Jaringan
     *
     * Menampilkan detail satu tugas/perintah jaringan, mencakup payload parameter perintah, waktu eksekusi, jumlah percobaan ulang, dan pesan kesalahan dari MikroTik jika gagal.
     *
     * @param NetworkJob $job
     * @return JsonResponse
     */
    public function job(NetworkJob $job): JsonResponse
    {
        return ApiResponse::success((new NetworkJobResource($job))->resolve());
    }

    /**
     * Coba Ulang Perintah Jaringan yang Gagal
     *
     * Menjadwalkan ulang eksekusi perintah jaringan yang sebelumnya gagal agar diproses kembali oleh worker antrean background.
     *
     * @param NetworkJob $job
     * @param NetworkQueueService $queue
     * @return JsonResponse
     */
    public function retry(NetworkJob $job, NetworkQueueService $queue): JsonResponse
    {
        try {
            $job = $queue->retry($job);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'NETWORK_JOB_STATE_CONFLICT', 409);
        }
        AuditService::log('retry_network_job', 'network', 'NetworkJob', $job->id);

        return ApiResponse::success((new NetworkJobResource($job))->resolve(), 'Network job queued for retry.', 202);
    }

    /**
     * Status Jaringan & PPPoE Pelanggan
     *
     * Mengambil data status jaringan pelanggan: akun PPPoE, profil bandwidth, IP address terkini, status konektivitas, serta job jaringan terakhir pelanggan.
     *
     * @param Customer $customer
     * @return JsonResponse
     */
    public function customer(Customer $customer): JsonResponse
    {
        $customer->load('pppAccount');

        return ApiResponse::success([
            'customer_id' => $customer->id,
            'service_status' => $customer->status,
            'pppoe' => $customer->pppAccount ? [
                'username' => $customer->pppAccount->username,
                'profile' => $customer->pppAccount->profile,
                'status' => $customer->pppAccount->status,
                'current_ip' => $customer->pppAccount->current_ip,
                'last_sync_at' => $customer->pppAccount->last_sync_at?->toISOString(),
            ] : null,
            'latest_job' => ($latest = NetworkJob::where('target_type', 'customer')->where('target_id', $customer->id)->latest()->first())
                ? (new NetworkJobResource($latest))->resolve()
                : null,
        ]);
    }

    /**
     * Sinkronisasi Profil PPPoE Pelanggan ke Router
     *
     * Menjadwalkan antrean sinkronisasi paksa profil PPPoE pelanggan dari database lokal ke router MikroTik RouterOS.
     *
     * @param Customer $customer
     * @param NetworkQueueService $queue
     * @return JsonResponse
     */
    public function sync(Customer $customer, NetworkQueueService $queue): JsonResponse
    {
        return $this->queue($customer, 'sync', $queue);
    }

    /**
     * Isolir Akses Internet Pelanggan
     *
     * Memblokir akses internet pelanggan yang menunggak tagihan dengan memindahkan profile PPPoE ke profil isolir di router MikroTik dan mengubah status pelanggan menjadi 'isolated'.
     *
     * @param Customer $customer
     * @param NetworkQueueService $queue
     * @return JsonResponse
     */
    public function isolate(Customer $customer, NetworkQueueService $queue): JsonResponse
    {
        if ($customer->status !== 'active') {
            return ApiResponse::error('Only active customers can be isolated.', 'CUSTOMER_STATE_CONFLICT', 409);
        }

        return $this->queue($customer, 'isolate', $queue);
    }

    /**
     * Buka Isolir Akses Internet Pelanggan
     *
     * Membuka blokir akses internet pelanggan setelah tagihan dilunasi, memulihkan profil PPPoE normal di router MikroTik, dan mengubah status pelanggan kembali menjadi 'active'.
     *
     * @param Customer $customer
     * @param NetworkQueueService $queue
     * @return JsonResponse
     */
    public function unisolate(Customer $customer, NetworkQueueService $queue): JsonResponse
    {
        if ($customer->status !== 'isolated') {
            return ApiResponse::error('Only isolated customers can be unisolated.', 'CUSTOMER_STATE_CONFLICT', 409);
        }
        if ($customer->outstanding_amount > 0) {
            return ApiResponse::error('Customer still has outstanding balance.', 'CUSTOMER_HAS_OUTSTANDING', 422);
        }

        return $this->queue($customer, 'unisolate', $queue);
    }

    /**
     * Daftar Sesi PPPoE Realtime MikroTik
     *
     * Menampilkan daftar seluruh akun PPPoE pelanggan yang terdaftar, status sesi online/offline pada router MikroTik, IP address aktif, uptime koneksi, status isolasi, filter status, pencarian, dan paginasi data.
     *
     * @param PppoeSessionQueryRequest $request
     * @param PppoeSessionService $service
     * @return JsonResponse
     */
    public function pppoeSessions(PppoeSessionQueryRequest $request, PppoeSessionService $service): JsonResponse
    {
        $result = $service->getPaginatedSessions($request->validated());

        return ApiResponse::success(
            PppoeSessionResource::collection($result['items'])->resolve(),
            $result['message'],
            200,
            $result['meta']
        );
    }

    /**
     * Ringkasan Sesi PPPoE & Status Router
     *
     * Menyediakan ringkasan cepat status koneksi router MikroTik, total akun PPPoE, jumlah sesi online, dan jumlah sesi offline untuk widget card dashboard Flutter.
     *
     * @param PppoeSessionService $service
     * @return JsonResponse
     */
    public function pppoeSessionSummary(PppoeSessionService $service): JsonResponse
    {
        $summary = $service->getSummary();

        return ApiResponse::success($summary, 'PPPoE session summary retrieved.');
    }

    private function queue(Customer $customer, string $command, NetworkQueueService $queue): JsonResponse
    {
        try {
            $job = DB::transaction(fn () => $queue->queueCustomer(
                Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail(),
                $command,
            ));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'NETWORK_OPERATION_FAILED', 422);
        }
        AuditService::log("queue_network_{$command}", 'network', 'Customer', $customer->id, null, ['network_job_id' => $job->id]);

        return ApiResponse::success((new NetworkJobResource($job))->resolve(), 'Network operation queued.', 202);
    }
}

