<?php

namespace App\Services;

use App\Models\PppAccount;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PppoeSessionService
{
    public function __construct(
        protected MikrotikService $mikrotikService
    ) {}

    public function getSessionData(bool $forceRefresh = false): array
    {
        return $this->mikrotikService->getPppoeSessionData($forceRefresh);
    }

    /**
     * Mengambil ringkasan status sesi PPPoE dan router untuk card dashboard mobile.
     *
     * @param bool $forceRefresh
     * @return array{
     *     router_status: string,
     *     total_accounts: int,
     *     online: int,
     *     offline: int,
     *     checked_at: string
     * }
     */
    public function getSummary(bool $forceRefresh = false): array
    {
        $sessionData = $this->getSessionData($forceRefresh);
        $activeByUsername = $sessionData['active_by_username'];

        $accounts = PppAccount::select(['id', 'username'])->get();
        $totalAccounts = $accounts->count();

        $onlineCount = $accounts->filter(function (PppAccount $acc) use ($activeByUsername) {
            return $activeByUsername->has($acc->username);
        })->count();

        $offlineCount = max(0, $totalAccounts - $onlineCount);

        return [
            'router_status' => $sessionData['status'] ?? ($sessionData['success'] ? 'online' : 'offline'),
            'total_accounts' => $totalAccounts,
            'online' => $onlineCount,
            'offline' => $offlineCount,
            'checked_at' => $sessionData['checked_at'],
        ];
    }

    /**
     * Mengambil daftar seluruh akun PPPoE dengan pencocokan status sesi aktif dari MikroTik,
     * filter status (all, online, offline), pencarian, sorting, dan paginasi.
     *
     * @param array $filters
     * @return array{
     *     items: array,
     *     message: string,
     *     meta: array
     * }
     */
    public function getPaginatedSessions(array $filters = []): array
    {
        $sessionData = $this->getSessionData();
        $activeByUsername = $sessionData['active_by_username'];
        $routerStatus = $sessionData['status'] ?? ($sessionData['success'] ? 'online' : 'offline');
        $checkedAt = $sessionData['checked_at'];

        // Eager load customer relationship to prevent N+1 query overhead
        $accounts = PppAccount::with('customer')->get();
        $totalAccounts = $accounts->count();

        $onlineCount = 0;
        $offlineCount = 0;

        $mapped = $accounts->map(function (PppAccount $account) use ($activeByUsername, &$onlineCount, &$offlineCount) {
            $activeConn = $activeByUsername->get($account->username);
            $isOnline = !empty($activeConn);

            if ($isOnline) {
                $onlineCount++;
            } else {
                $offlineCount++;
            }

            $isIsolated = ($account->profile === 'ISOLIR')
                || ($account->customer?->status === 'isolated')
                || ($account->status === 'isolated');

            $session = null;
            if ($isOnline && is_array($activeConn)) {
                $session = [
                    'address' => $activeConn['address'] ?? null,
                    'uptime' => $activeConn['uptime'] ?? null,
                    'caller_id' => $activeConn['caller-id'] ?? $activeConn['caller_id'] ?? null,
                    'service' => $activeConn['service'] ?? 'pppoe',
                ];

                if (isset($activeConn['session-id']) || isset($activeConn['session_id'])) {
                    $session['session_id'] = $activeConn['session-id'] ?? $activeConn['session_id'];
                }
                if (isset($activeConn['encoding'])) {
                    $session['encoding'] = $activeConn['encoding'];
                }
                if (isset($activeConn['radius'])) {
                    $session['radius'] = filter_var($activeConn['radius'], FILTER_VALIDATE_BOOLEAN);
                }
                if (isset($activeConn['limit-bytes-in']) || isset($activeConn['limit_bytes_in'])) {
                    $session['limit_bytes_in'] = $activeConn['limit-bytes-in'] ?? $activeConn['limit_bytes_in'];
                }
                if (isset($activeConn['limit-bytes-out']) || isset($activeConn['limit_bytes_out'])) {
                    $session['limit_bytes_out'] = $activeConn['limit-bytes-out'] ?? $activeConn['limit_bytes_out'];
                }
            }

            return [
                'customer_id' => $account->customer?->id ?? $account->customer_id,
                'customer_code' => $account->customer?->customer_id ?? ('CUST-' . $account->customer_id),
                'customer_name' => $account->customer?->name ?? $account->username,
                'username' => $account->username,
                'profile' => $account->profile,
                'is_isolated' => $isIsolated,
                'is_online' => $isOnline,
                'session' => $session,
                'created_at' => $account->created_at?->toIso8601String(),
            ];
        });

        // 1. Filter Status (all, online, offline)
        $statusFilter = $filters['status'] ?? 'all';
        if ($statusFilter === 'online') {
            $mapped = $mapped->where('is_online', true);
        } elseif ($statusFilter === 'offline') {
            $mapped = $mapped->where('is_online', false);
        }

        // 2. Filter Search (customer name, customer code, pppoe username)
        if (!empty($filters['search'])) {
            $search = Str::lower(trim((string) $filters['search']));
            $mapped = $mapped->filter(function (array $item) use ($search) {
                return str_contains(Str::lower($item['customer_name']), $search)
                    || str_contains(Str::lower($item['customer_code']), $search)
                    || str_contains(Str::lower($item['username']), $search);
            });
        }

        // 3. Sorting
        $sort = $filters['sort'] ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        $mapped = match ($field) {
            'customer_name' => $direction === 'desc'
                ? $mapped->sortByDesc('customer_name', SORT_NATURAL | SORT_FLAG_CASE)
                : $mapped->sortBy('customer_name', SORT_NATURAL | SORT_FLAG_CASE),
            'customer_code' => $direction === 'desc'
                ? $mapped->sortByDesc('customer_code', SORT_NATURAL | SORT_FLAG_CASE)
                : $mapped->sortBy('customer_code', SORT_NATURAL | SORT_FLAG_CASE),
            'username' => $direction === 'desc'
                ? $mapped->sortByDesc('username', SORT_NATURAL | SORT_FLAG_CASE)
                : $mapped->sortBy('username', SORT_NATURAL | SORT_FLAG_CASE),
            'status' => $direction === 'desc'
                ? $mapped->sortByDesc('is_online')
                : $mapped->sortBy('is_online'),
            'created_at' => $direction === 'desc'
                ? $mapped->sortByDesc('created_at')
                : $mapped->sortBy('created_at'),
            default => $mapped->sortByDesc('created_at'),
        };

        // 4. Pagination
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $filteredTotal = $mapped->count();

        $items = $mapped->slice(($page - 1) * $perPage, $perPage)->values()->all();

        $paginator = new LengthAwarePaginator(
            $items,
            $filteredTotal,
            $perPage,
            $page
        );

        $message = ($routerStatus === 'offline')
            ? 'Router is currently offline. Showing cached/account state.'
            : 'PPPoE sessions retrieved.';

        return [
            'items' => $items,
            'message' => $message,
            'meta' => [
                'router_status' => $routerStatus,
                'total_accounts' => $totalAccounts,
                'online' => $onlineCount,
                'offline' => $offlineCount,
                'checked_at' => $checkedAt,
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
