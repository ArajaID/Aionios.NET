<?php

namespace App\Console\Commands;

use App\Services\MikrotikService;
use Illuminate\Console\Command;

class ProcessNetworkJobsCommand extends Command
{
    protected $signature = 'isp:process-network-jobs';
    protected $description = 'Retry and process pending MikroTik network jobs';

    public function handle(MikrotikService $mikrotikService): int
    {
        $this->info("Memproses antrean sinkronisasi jaringan MikroTik...");

        $count = $mikrotikService->processPendingJobs();

        $this->info("Selesai! {$count} pekerjaan jaringan telah diproses.");
        return self::SUCCESS;
    }
}
