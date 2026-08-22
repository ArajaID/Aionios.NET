<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\NetworkJob;
use App\Models\NetworkLog;
use App\Models\PppAccount;
use App\Services\AuditService;
use App\Services\MikrotikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MikrotikController extends Controller
{
    public function index(): Response
    {
        $router = MikrotikRouter::where('is_active', true)->first() ?? MikrotikRouter::first();
        $pppAccounts = PppAccount::with('customer')->latest()->get();
        $pendingJobs = NetworkJob::where('status', 'pending')->latest()->get();
        $networkLogs = NetworkLog::with(['router', 'executor'])->latest()->take(20)->get();

        return Inertia::render('Network/Mikrotik', [
            'router_status' => $router,
            'ppp_accounts' => $pppAccounts,
            'pending_jobs' => $pendingJobs,
            'network_logs' => $networkLogs,
        ]);
    }

    public function updateRouter(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'host' => 'required|string|max:100',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:100',
            'password' => 'nullable|string|max:255',
            'timeout' => 'required|integer|min:1|max:30',
            'api_type' => 'required|in:rest,api,api_ssl',
            'is_active' => 'required|boolean',
        ]);

        $router = MikrotikRouter::first();
        if (empty($validated['password']) && $router) {
            unset($validated['password']);
        }

        $connectionChanged = !$router
            || $router->host !== $validated['host']
            || $router->port !== $validated['port']
            || $router->username !== $validated['username']
            || $router->api_type !== $validated['api_type']
            || array_key_exists('password', $validated);

        if ($connectionChanged) {
            $validated['status'] = 'unknown';
            $validated['last_connected_at'] = null;
            $validated['resource_data'] = null;
        }

        if ($router) {
            $router->update($validated);
        } else {
            $router = MikrotikRouter::create($validated);
        }

        AuditService::log('update_mikrotik_config', 'network', 'MikrotikRouter', $router->id, null, [
            'name' => $router->name,
            'host' => $router->host,
            'port' => $router->port,
            'username' => $router->username,
            'timeout' => $router->timeout,
            'api_type' => $router->api_type,
            'is_active' => $router->is_active,
            'password_changed' => array_key_exists('password', $validated),
        ]);

        return back()->with('success', 'Konfigurasi router MikroTik berhasil disimpan.');
    }

    public function testConnection(MikrotikService $mikrotikService): RedirectResponse
    {
        $result = $mikrotikService->testConnection();
        if ($result['success']) {
            return back()->with('success', $result['message']);
        }
        return back()->with('warning', $result['message']);
    }

    public function resource(MikrotikService $mikrotikService): JsonResponse
    {
        $result = $mikrotikService->testConnection();
        $activeConnections = $result['success']
            ? $mikrotikService->getActiveConnections()
            : [];

        return response()->json([
            ...$result,
            'active_connections' => $activeConnections,
            'checked_at' => now()->toIso8601String(),
        ], $result['success'] ? 200 : 503);
    }

    public function processJobs(MikrotikService $mikrotikService): RedirectResponse
    {
        $count = $mikrotikService->processPendingJobs();
        return back()->with('success', "Berhasil memproses {$count} antrean sinkronisasi jaringan.");
    }

    public function toggleIsolate(Request $request, Customer $customer, MikrotikService $mikrotikService): RedirectResponse
    {
        if ($customer->status === 'isolated') {
            $mikrotikService->unisolateCustomer($customer);
            return back()->with('success', "Pelanggan {$customer->name} berhasil di-unisolir dan dikembalikan ke profile aktif.");
        } else {
            $mikrotikService->isolateCustomer($customer);
            return back()->with('warning', "Pelanggan {$customer->name} berhasil dipindahkan ke PPP Profile ISOLIR.");
        }
    }
}
