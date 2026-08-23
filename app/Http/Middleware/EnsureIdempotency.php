<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            return $next($request);
        }

        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            return ApiResponse::error('Invalid Idempotency-Key header.', 'VALIDATION_ERROR', 422, [
                'fields' => ['Idempotency-Key' => ['The Idempotency-Key must be 8-100 safe characters.']],
            ]);
        }

        $payload = $request->all();
        $this->sortRecursive($payload);
        $fingerprint = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $scope = [
            'user_id' => $request->user()->id,
            'key' => $key,
            'method' => $request->method(),
            'uri' => '/'.$request->path(),
        ];

        $record = ApiIdempotencyKey::where($scope)->first();
        if ($record) {
            return $this->existingResponse($record, $fingerprint);
        }

        try {
            $record = ApiIdempotencyKey::create($scope + [
                'request_fingerprint' => $fingerprint,
                'state' => 'processing',
                'expires_at' => now()->addDay(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $record = ApiIdempotencyKey::where($scope)->firstOrFail();

            return $this->existingResponse($record, $fingerprint);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $record->delete();
            throw $e;
        }

        $decoded = json_decode((string) $response->getContent(), true);
        $record->update([
            'state' => 'completed',
            'status_code' => $response->getStatusCode(),
            'response_payload' => is_array($decoded) ? $decoded : ['success' => true],
        ]);

        return $response;
    }

    private function existingResponse(ApiIdempotencyKey $record, string $fingerprint): Response
    {
        if (! hash_equals($record->request_fingerprint, $fingerprint)) {
            return ApiResponse::error(
                'The Idempotency-Key was already used with a different payload.',
                'IDEMPOTENCY_CONFLICT',
                409,
            );
        }

        if ($record->state !== 'completed') {
            return ApiResponse::error(
                'The original request is still being processed.',
                'IDEMPOTENCY_REQUEST_IN_PROGRESS',
                409,
            );
        }

        $response = response()->json($record->response_payload, $record->status_code ?? 200);
        $response->headers->set('Idempotent-Replayed', 'true');

        return $response;
    }

    private function sortRecursive(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }
    }
}
