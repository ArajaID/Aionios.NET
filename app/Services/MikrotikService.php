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
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
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
            $response = Http::timeout($this->router->timeout ?? 5)
                ->withBasicAuth($this->router->username, $this->router->password ?? '')
                ->get($url);

            if ($response->successful()) {
                $resourceData = $response->json();
                if (is_array($resourceData) && array_is_list($resourceData)) {
                    $resourceData = $resourceData[0] ?? [];
                }

                $this->router->update([
                    'status' => 'online',
                    'last_connected_at' => now(),
                    'resource_data' => $resourceData,
                ]);

                return [
                    'success' => true,
                    'message' => 'Terhubung ke MikroTik RouterOS 7.24.',
                    'status' => 'online',
                    'data' => $resourceData,
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

    public function getActiveConnections(): array
    {
        if (!$this->router || !$this->router->is_active) {
            return [];
        }

        try {
            $url = "http://{$this->router->host}:{$this->router->port}/rest/ppp/active";
            $response = Http::timeout($this->router->timeout ?? 5)
                ->retry(2, 500, fn (Throwable $exception) => $exception instanceof ConnectionException)
                ->withBasicAuth($this->router->username, $this->router->password ?? '')
                ->get($url);

            if ($response->successful()) {
                $connections = $response->json();
                return is_array($connections) ? array_values($connections) : [];
            }

            Log::warning("Failed to fetch MikroTik active PPP connections: HTTP {$response->status()} {$response->body()}");
        } catch (Throwable $e) {
            Log::warning("Failed to fetch MikroTik active PPP connections: {$e->getMessage()}");
        }

        return [];
    }

    public function deletePppProfile(string $profileName): array
    {
        if (in_array($profileName, ['default', 'default-encryption', 'ISOLIR'], true)) {
            return [
                'success' => false,
                'message' => "PPP Profile sistem {$profileName} tidak boleh dihapus.",
            ];
        }

        if (!$this->router || !$this->router->is_active) {
            return [
                'success' => false,
                'message' => 'Router MikroTik tidak aktif atau tidak tersedia.',
            ];
        }

        try {
            $profileUrl = "http://{$this->router->host}:{$this->router->port}/rest/ppp/profile";
            $client = Http::timeout($this->router->timeout ?? 5)
                ->retry(2, 500, fn (Throwable $exception) => $exception instanceof ConnectionException)
                ->withBasicAuth($this->router->username, $this->router->password ?? '');

            $lookup = $client->get($profileUrl, ['name' => $profileName]);
            if (!$lookup->successful()) {
                return [
                    'success' => false,
                    'message' => "Gagal mencari PPP Profile {$profileName}: HTTP {$lookup->status()} {$lookup->body()}",
                ];
            }

            $profile = collect($lookup->json())
                ->first(fn (array $item) => ($item['name'] ?? null) === $profileName);

            if (!$profile) {
                return [
                    'success' => true,
                    'message' => "PPP Profile {$profileName} sudah tidak ada di MikroTik.",
                ];
            }

            $profileId = $profile['.id'] ?? null;
            if (!$profileId) {
                return [
                    'success' => false,
                    'message' => "Resource ID PPP Profile {$profileName} tidak ditemukan.",
                ];
            }

            $response = $client->delete($profileUrl . '/' . $profileId);
            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => "MikroTik menolak penghapusan profile {$profileName}: HTTP {$response->status()} {$response->body()}",
                ];
            }

            NetworkLog::create([
                'action' => 'delete_profile',
                'router_id' => $this->router->id,
                'status' => 'success',
                'request_data' => ['profile' => $profileName, 'resource_id' => $profileId],
                'response_data' => $response->json(),
                'executed_by' => auth()->id(),
            ]);

            return [
                'success' => true,
                'message' => "PPP Profile {$profileName} berhasil dihapus dari MikroTik.",
            ];
        } catch (Throwable $e) {
            Log::error("Failed to delete MikroTik PPP profile {$profileName}: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => "Gagal terhubung ke MikroTik: {$e->getMessage()}",
            ];
        }
    }

    public function createPppSecret(PppAccount $account): bool
    {
        $package = $account->customer?->package;
        $payload = [
            'name' => $account->username,
            'password' => $account->password,
            'profile' => $account->profile,
            'service' => 'pppoe',
            'comment' => "Customer ID: {$account->customer->customer_id} - {$account->customer->name}",
            '_profile_rate_limit' => $package
                ? "{$package->upload_speed_mbps}M/{$package->download_speed_mbps}M"
                : null,
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
        $failureMessage = 'Router tidak aktif atau tidak tersedia.';

        // Always attempt an active router. A persisted offline/unknown status may be stale.
        if ($this->router && $this->router->is_active) {
            try {
                $res = $this->sendCommand($command, $payload);

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

                    $this->router->update([
                        'status' => 'online',
                        'last_connected_at' => now(),
                    ]);

                    NetworkJob::where([
                        'command' => $command,
                        'target_type' => $targetType,
                        'target_id' => $targetId,
                        'status' => 'pending',
                    ])->update([
                        'status' => 'success',
                        'error_message' => null,
                    ]);

                    return true;
                }

                $failureMessage = "HTTP {$res->status()}: " . mb_substr($res->body(), 0, 500);
                Log::warning("MikroTik command {$command} rejected: {$failureMessage}");
            } catch (Throwable $e) {
                $failureMessage = $e->getMessage();
                Log::error("Direct MikroTik command {$command} failed: {$failureMessage}");
            }
        }

        if ($this->router) {
            $this->router->update(['status' => 'offline']);
        }

        // Keep one pending job per command/target so retries stay idempotent.
        NetworkJob::firstOrCreate(
            [
                'command' => $command,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'status' => 'pending',
            ],
            [
                'payload' => $payload,
                'attempts' => 0,
                'error_message' => $failureMessage,
            ]
        );

        NetworkLog::create([
            'action' => $command,
            'router_id' => $this->router?->id,
            'ppp_username' => $pppUsername,
            'status' => 'queued_offline',
            'request_data' => $payload,
            'response_data' => [
                'message' => 'Command queued in pending sync',
                'error' => $failureMessage,
            ],
            'executed_by' => auth()->id(),
        ]);

        Notification::create([
            'role' => 'admin_jaringan',
            'type' => 'warning',
            'title' => 'MikroTik Offline / Pending Sync',
            'message' => "Perintah [{$command}] untuk {$pppUsername} masuk antrean sinkronisasi karena MikroTik tidak dapat dihubungi.",
            'link' => '/mikrotik',
        ]);

        return false;
    }

    public function processPendingJobs(): int
    {
        $jobs = NetworkJob::where('status', 'pending')->where('attempts', '<', 5)->get();
        $processed = 0;

        foreach ($jobs as $job) {
            $job->update(['status' => 'processing', 'attempts' => $job->attempts + 1]);

            try {
                if (!$this->router || !$this->router->is_active) {
                    throw new RuntimeException('Tidak ada router MikroTik aktif.');
                }

                $response = $this->sendCommand($job->command, $job->payload ?? []);

                if (!$response->successful()) {
                    throw new RuntimeException("HTTP {$response->status()}: " . mb_substr($response->body(), 0, 500));
                }

                $job->update([
                    'status' => 'success',
                    'error_message' => null,
                ]);

                if ($job->command === 'create_secret' && $job->target_type === 'ppp_account') {
                    PppAccount::whereKey($job->target_id)->update(['last_sync_at' => now()]);
                }

                NetworkLog::create([
                    'action' => $job->command,
                    'router_id' => $this->router->id,
                    'ppp_username' => $job->payload['name'] ?? $job->payload['username'] ?? null,
                    'status' => 'success',
                    'request_data' => $job->payload,
                    'response_data' => $response->json(),
                    'executed_by' => auth()->id(),
                ]);

                $this->router->update([
                    'status' => 'online',
                    'last_connected_at' => now(),
                ]);

                $processed++;
            } catch (Throwable $e) {
                $job->update([
                    'status' => $job->attempts >= 5 ? 'failed' : 'pending',
                    'error_message' => mb_substr($e->getMessage(), 0, 1000),
                ]);

                Log::error("MikroTik queued command {$job->command} failed: {$e->getMessage()}");
            }
        }

        return $processed;
    }

    protected function sendCommand(string $command, array $payload)
    {
        if (!$this->router) {
            throw new RuntimeException('Router MikroTik tidak tersedia.');
        }

        $baseUrl = "http://{$this->router->host}:{$this->router->port}/rest/ppp/secret";
        $client = Http::timeout($this->router->timeout ?? 5)
            ->retry(2, 500, fn (Throwable $exception) => $exception instanceof ConnectionException)
            ->withBasicAuth($this->router->username, $this->router->password ?? '');

        if ($command === 'create_secret') {
            $rateLimit = $payload['_profile_rate_limit'] ?? null;
            unset($payload['_profile_rate_limit']);

            $this->ensurePppProfile($payload['profile'] ?? 'default', $rateLimit, $client);

            $username = $payload['name'] ?? null;
            if ($username) {
                $lookup = $client->get($baseUrl, ['name' => $username]);
                if ($lookup->successful()) {
                    $existing = collect($lookup->json())->first(fn (array $item) => ($item['name'] ?? null) === $username);
                    if ($existing && isset($existing['.id'])) {
                        $secretId = $existing['.id'];
                        $patchPayload = $payload;
                        unset($patchPayload['name']);
                        return $client->patch($baseUrl . '/' . $secretId, $patchPayload);
                    }
                }
            }

            $putResponse = $client->put($baseUrl, $payload);
            if (!$putResponse->successful() && str_contains($putResponse->body(), 'already exists') && $username) {
                $lookup = $client->get($baseUrl, ['name' => $username]);
                if ($lookup->successful()) {
                    $existing = collect($lookup->json())->first(fn (array $item) => ($item['name'] ?? null) === $username);
                    if ($existing && isset($existing['.id'])) {
                        $secretId = $existing['.id'];
                        $patchPayload = $payload;
                        unset($patchPayload['name']);
                        return $client->patch($baseUrl . '/' . $secretId, $patchPayload);
                    }
                }
            }

            return $putResponse;
        }

        $username = $payload['username'] ?? $payload['name'] ?? null;
        if (!$username) {
            throw new RuntimeException("Username PPPoE tidak tersedia untuk perintah {$command}.");
        }

        $lookup = $client->get($baseUrl, ['name' => $username]);
        if (!$lookup->successful()) {
            throw new RuntimeException("Gagal mencari PPP Secret {$username}: HTTP {$lookup->status()} {$lookup->body()}");
        }

        $secret = collect($lookup->json())->first(fn (array $item) => ($item['name'] ?? null) === $username);
        $secretId = $secret['.id'] ?? null;
        if (!$secretId) {
            throw new RuntimeException("PPP Secret {$username} tidak ditemukan di MikroTik.");
        }

        unset($payload['username'], $payload['name']);

        // RouterOS REST resource IDs use the literal `*` prefix (for example `*11`).
        return $client->patch($baseUrl . '/' . $secretId, $payload);
    }

    protected function ensurePppProfile(string $profileName, ?string $rateLimit, $client): void
    {
        if ($profileName === 'default' || $profileName === 'default-encryption') {
            return;
        }

        $profileUrl = "http://{$this->router->host}:{$this->router->port}/rest/ppp/profile";
        $lookup = $client->get($profileUrl, ['name' => $profileName]);

        if (!$lookup->successful()) {
            throw new RuntimeException("Gagal memeriksa PPP Profile {$profileName}: HTTP {$lookup->status()} {$lookup->body()}");
        }

        $profileExists = collect($lookup->json())
            ->contains(fn (array $item) => ($item['name'] ?? null) === $profileName);

        if ($profileExists) {
            return;
        }

        $profilePayload = ['name' => $profileName];
        if ($rateLimit) {
            $profilePayload['rate-limit'] = $rateLimit;
        }

        $create = $client->put($profileUrl, $profilePayload);
        if (!$create->successful()) {
            throw new RuntimeException("Gagal membuat PPP Profile {$profileName}: HTTP {$create->status()} {$create->body()}");
        }
    }
}
