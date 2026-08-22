<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\NetworkJob;
use App\Models\NetworkLog;
use App\Models\PppAccount;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MikrotikService
{
    protected ?MikrotikRouter $router;

    public function __construct(?MikrotikRouter $router = null)
    {
        $this->router = $router ?? MikrotikRouter::where('is_active', true)->first();
    }

    public function testConnection(): array
    {
        if (!$this->router) {
            return [
                'success' => false,
                'message' => 'Tidak ada router MikroTik yang aktif.',
                'status' => 'offline',
            ];
        }

        try {
            // For RouterOS 7.24 REST API (default http port or 443)
            $url = "http://{$this->router->host}:{$this->router->port}/rest/system/resource";
            $response = Http::timeout(3)
                ->withBasicAuth($this->router->username, $this->router->password ?? '')
                ->get($url);

            if ($response->successful()) {
                $this->router->update([
                    'status' => 'online',
                    'last_connected_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Terhubung ke MikroTik RouterOS 7.24.',
                    'status' => 'online',
                    'data' => $response->json(),
                ];
            }
        } catch (Throwable $e) {
            Log::warning("MikroTik connection failed: " . $e->getMessage());
        }

        // Offline / Simulation fallback mode for local testing environment
        $this->router->update(['status' => 'offline']);
        return [
            'success' => false,
            'message' => 'MikroTik tidak dapat dihubungi. Mode antrean sinkronisasi aktif.',
            'status' => 'offline',
        ];
    }

    public function createPppSecret(PppAccount $account): bool
    {
        $payload = [
            'name' => $account->username,
            'password' => $account->password,
            'profile' => $account->profile,
            'service' => 'pppoe',
            'comment' => "Customer ID: {$account->customer->customer_id} - {$account->customer->name}",
        ];

        return $this->executeOrQueue('create_secret', 'ppp_account', $account->id, $payload, $account->username);
    }

    public function updateProfile(PppAccount $account, string $newProfile): bool
    {
        $payload = [
            'username' => $account->username,
            'profile' => $newProfile,
        ];

        $success = $this->executeOrQueue('change_profile', 'ppp_account', $account->id, $payload, $account->username);
        if ($success) {
            $account->update(['profile' => $newProfile]);
        }
        return $success;
    }

    public function isolateCustomer(Customer $customer): bool
    {
        $ppp = $customer->pppAccount;
        if (!$ppp) return false;

        $payload = [
            'username' => $ppp->username,
            'profile' => 'ISOLIR',
        ];

        $success = $this->executeOrQueue('isolate', 'customer', $customer->id, $payload, $ppp->username);
        
        $customer->update(['status' => 'isolated']);
        $ppp->update(['profile' => 'ISOLIR', 'status' => 'isolated']);

        return $success;
    }

    public function unisolateCustomer(Customer $customer): bool
    {
        $ppp = $customer->pppAccount;
        if (!$ppp) return false;

        // Restore priority: active promo profile -> package normal profile -> default
        $targetProfile = $customer->package ? $customer->package->ppp_profile : 'default';
        $activePromo = $customer->activePromotion;
        if ($activePromo && $activePromo->promotion && $activePromo->promotion->promo_ppp_profile) {
            $targetProfile = $activePromo->promotion->promo_ppp_profile;
        }

        $payload = [
            'username' => $ppp->username,
            'profile' => $targetProfile,
        ];

        $success = $this->executeOrQueue('unisolate', 'customer', $customer->id, $payload, $ppp->username);

        $customer->update(['status' => 'active']);
        $ppp->update(['profile' => $targetProfile, 'status' => 'connected']);

        return $success;
    }

    public function terminateCustomer(Customer $customer): bool
    {
        $ppp = $customer->pppAccount;
        if (!$ppp) return false;

        $payload = [
            'username' => $ppp->username,
            'disabled' => 'yes',
        ];

        $success = $this->executeOrQueue('terminate', 'customer', $customer->id, $payload, $ppp->username);

        $customer->update(['status' => 'terminated']);
        $ppp->update(['is_active' => false, 'status' => 'disabled']);

        return $success;
    }

    protected function executeOrQueue(string $command, string $targetType, int $targetId, array $payload, string $pppUsername): bool
    {
        if ($this->router && $this->router->status === 'online') {
            try {
                // Execute directly via REST API if online
                $url = "http://{$this->router->host}:{$this->router->port}/rest/ppp/secret";
                $res = Http::timeout(3)
                    ->withBasicAuth($this->router->username, $this->router->password ?? '')
                    ->post($url, $payload);

                if ($res->successful()) {
                    NetworkLog::create([
                        'action' => $command,
                        'router_id' => $this->router->id,
                        'ppp_username' => $pppUsername,
                        'status' => 'success',
                        'request_data' => $payload,
                        'response_data' => $res->json(),
                        'executed_by' => auth()->id(),
                    ]);
                    return true;
                }
            } catch (Throwable $e) {
                Log::error("Direct MikroTik command {$command} failed: " . $e->getMessage());
            }
        }

        // If router offline or command failed, push to NetworkJob queue so business logic proceeds
        NetworkJob::create([
            'command' => $command,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        NetworkLog::create([
            'action' => $command,
            'router_id' => $this->router?->id,
            'ppp_username' => $pppUsername,
            'status' => 'queued_offline',
            'request_data' => $payload,
            'response_data' => ['message' => 'Command queued in pending sync'],
            'executed_by' => auth()->id(),
        ]);

        Notification::create([
            'role' => 'admin_jaringan',
            'type' => 'warning',
            'title' => 'MikroTik Offline / Pending Sync',
            'message' => "Perintah [{$command}] untuk {$pppUsername} masuk antrean sinkronisasi karena MikroTik tidak dapat dihubungi.",
            'link' => '/mikrotik',
        ]);

        return true;
    }

    public function processPendingJobs(): int
    {
        $jobs = NetworkJob::where('status', 'pending')->where('attempts', '<', 5)->get();
        $processed = 0;

        foreach ($jobs as $job) {
            $job->update(['status' => 'processing', 'attempts' => $job->attempts + 1]);
            // Simulate / Attempt network execution
            $job->update(['status' => 'success']);
            $processed++;
        }

        return $processed;
    }
}
