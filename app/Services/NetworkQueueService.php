<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NetworkJob;
use App\Models\PppAccount;
use RuntimeException;

class NetworkQueueService
{
    public function queueCreateSecret(PppAccount $account): NetworkJob
    {
        $package = $account->customer?->package;

        return $this->create('create_secret', 'ppp_account', $account->id, [
            'name' => $account->username,
            'password' => $account->password,
            'profile' => $account->profile,
            'service' => 'pppoe',
            'comment' => "Customer ID: {$account->customer?->customer_id} - {$account->customer?->name}",
            '_profile_rate_limit' => $package
                ? "{$package->upload_speed_mbps}M/{$package->download_speed_mbps}M"
                : null,
        ]);
    }

    public function queueCustomer(Customer $customer, string $command): NetworkJob
    {
        $customer->loadMissing(['pppAccount', 'package', 'activePromotion.promotion']);
        $ppp = $customer->pppAccount;
        if (! $ppp) {
            throw new RuntimeException('Customer does not have a PPPoE account.');
        }

        $payload = ['username' => $ppp->username];

        if ($command === 'isolate') {
            $payload['profile'] = 'ISOLIR';
            $customer->update(['status' => 'isolated']);
            $ppp->update(['profile' => 'ISOLIR', 'status' => 'isolated']);
        } elseif (in_array($command, ['unisolate', 'sync', 'reactivate'], true)) {
            $profile = $customer->package?->ppp_profile ?? 'default';
            $promotionProfile = $customer->activePromotion?->promotion?->promo_ppp_profile;
            if ($promotionProfile) {
                $profile = $promotionProfile;
            }
            $payload['profile'] = $profile;
            $customer->update(['status' => 'active']);
            $ppp->update(['profile' => $profile, 'status' => 'connected', 'is_active' => true]);
        } elseif ($command === 'terminate') {
            $payload['disabled'] = 'yes';
            $customer->update(['status' => 'terminated']);
            $ppp->update(['status' => 'disabled', 'is_active' => false]);
        } else {
            throw new RuntimeException('Unsupported network command.');
        }

        return $this->create($command, 'customer', $customer->id, $payload);
    }

    public function retry(NetworkJob $job): NetworkJob
    {
        if (! in_array($job->status, ['failed', 'pending'], true)) {
            throw new RuntimeException('Only failed or pending network jobs can be retried.');
        }

        $job->update([
            'status' => 'pending',
            'attempts' => 0,
            'error_message' => null,
        ]);

        return $job->fresh();
    }

    private function create(string $command, string $targetType, int $targetId, array $payload): NetworkJob
    {
        return NetworkJob::create([
            'command' => $command,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }
}
