<?php

namespace App\Console\Commands;

use App\Models\CustomerPromotion;
use App\Models\Notification;
use App\Services\AuditService;
use App\Services\MikrotikService;
use Illuminate\Console\Command;

class CheckPromotionsCommand extends Command
{
    protected $signature = 'isp:check-promotions';
    protected $description = 'Check expired customer promotions and restore normal PPP profile';

    public function handle(MikrotikService $mikrotikService): int
    {
        $this->info("Mengecek promo pelanggan yang telah berakhir...");

        $expiredPromos = CustomerPromotion::where('status', 'active')
            ->where('end_date', '<', now()->toDateString())
            ->with(['customer.package', 'promotion'])
            ->get();

        $count = 0;
        foreach ($expiredPromos as $cp) {
            $cp->update(['status' => 'expired']);

            $customer = $cp->customer;
            if ($customer && $customer->status === 'active' && $customer->pppAccount) {
                // Restore package profile
                $normalProfile = $customer->package ? $customer->package->ppp_profile : 'default';
                $mikrotikService->updateProfile($customer->pppAccount, $normalProfile);
            }

            Notification::create([
                'role' => 'admin_jaringan',
                'type' => 'info',
                'title' => 'Promo Pelanggan Berakhir',
                'message' => "Promo {$cp->promotion->name} untuk pelanggan {$customer->name} telah berakhir. Profile dikembalikan ke normal.",
                'link' => "/customers/{$customer->id}",
            ]);

            $count++;
        }

        $this->info("Selesai! {$count} promo pelanggan telah diproses.");
        return self::SUCCESS;
    }
}
