<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PreviewReferenceService
{
    public function issue(string $type, int $userId, array $payload): string
    {
        $reference = (string) Str::uuid();
        Cache::put($this->key($type, $reference), [
            'user_id' => $userId,
            'payload' => $payload,
        ], now()->addMinutes(10));

        return $reference;
    }

    public function get(string $type, string $reference, int $userId): ?array
    {
        $stored = Cache::get($this->key($type, $reference));
        if (! is_array($stored) || ($stored['user_id'] ?? null) !== $userId) {
            return null;
        }

        return $stored['payload'] ?? null;
    }

    public function forget(string $type, string $reference): void
    {
        Cache::forget($this->key($type, $reference));
    }

    private function key(string $type, string $reference): string
    {
        return "api-preview:{$type}:{$reference}";
    }
}
