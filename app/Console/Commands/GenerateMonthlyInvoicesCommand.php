<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

class GenerateMonthlyInvoicesCommand extends Command
{
    protected $signature = 'isp:generate-invoices {period? : The billing period in YYYY-MM format}';
    protected $description = 'Generate monthly recurring invoices for active and isolated customers (Runs on 1st of month)';

    public function handle(BillingService $billingService): int
    {
        $period = $this->argument('period') ?? now()->format('Y-m');
        $this->info("Menerbitkan tagihan bulanan periode {$period}...");

        $result = $billingService->generateMonthlyInvoices($period);

        $this->info("Selesai! Diterbitkan: {$result['generated']}, Dilewati: {$result['skipped']}");
        return self::SUCCESS;
    }
}
