<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class IsolationService
{
    protected MikrotikService $mikrotikService;

    public function __construct(MikrotikService $mikrotikService)
    {
        $this->mikrotikService = $mikrotikService;
    }

    public function processAutoIsolation(): array
    {
        $today = now();
        // Overdue unpaid invoices
        $overdueCustomers = Customer::where('status', 'active')
            ->whereHas('invoices', function ($query) use ($today) {
                $query->whereIn('status', ['unpaid', 'overdue'])
                    ->where('due_date', '<', $today);
            })
            ->with(['invoices', 'pppAccount'])
            ->get();

        $isolatedCount = 0;

        foreach ($overdueCustomers as $customer) {
            $this->mikrotikService->isolateCustomer($customer);
            $isolatedCount++;

            Notification::create([
                'role' => 'admin_jaringan',
                'type' => 'danger',
                'title' => 'Pelanggan Diisolir Otomatis',
                'message' => "Pelanggan {$customer->name} ({$customer->customer_id}) diisolir karena menunggak tagihan.",
                'link' => "/customers/{$customer->id}",
            ]);
        }

        AuditService::log(
            'auto_isolation_run',
            'network',
            null,
            null,
            null,
            ['isolated_count' => $isolatedCount, 'timestamp' => now()->toDateTimeString()]
        );

        return [
            'isolated_count' => $isolatedCount,
            'processed_at' => now()->toDateTimeString(),
        ];
    }
}
