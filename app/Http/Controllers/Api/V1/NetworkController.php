<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NetworkJobResource;
use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\NetworkJob;
use App\Services\AuditService;
use App\Services\NetworkQueueService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NetworkController extends Controller
{
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

    public function job(NetworkJob $job): JsonResponse
    {
        return ApiResponse::success((new NetworkJobResource($job))->resolve());
    }

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

    public function sync(Customer $customer, NetworkQueueService $queue): JsonResponse
    {
        return $this->queue($customer, 'sync', $queue);
    }

    public function isolate(Customer $customer, NetworkQueueService $queue): JsonResponse
    {
        if ($customer->status !== 'active') {
            return ApiResponse::error('Only active customers can be isolated.', 'CUSTOMER_STATE_CONFLICT', 409);
        }

        return $this->queue($customer, 'isolate', $queue);
    }

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
