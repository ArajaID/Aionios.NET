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
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MikrotikController extends Controller
{
    public function index(): Response
    {
        $router = MikrotikRouter::first();
        $pppAccounts = PppAccount::with('customer')->latest()->paginate(15);
        $pendingJobs = NetworkJob::latest()->paginate(10, ['*'], 'jobs_page');
        $networkLogs = NetworkLog::with(['router', 'executor'])->latest()->take(20)->get();

        return Inertia::render('Network/Mikrotik', [
            'router' => $router,
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
            'password' => 'nullable|string',
            'api_type' => 'required|in:rest,api,api_ssl',
            'is_active' => 'required|boolean',
        ]);

        $router = MikrotikRouter::first();
        if (!$validated['password'] && $router) {
            unset($validated['password']);
        }

        if ($router) {
            $router->update($validated);
        } else {
            $router = MikrotikRouter::create($validated);
        }

        AuditService::log('update_mikrotik_config', 'network', 'MikrotikRouter', $router->id);

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
