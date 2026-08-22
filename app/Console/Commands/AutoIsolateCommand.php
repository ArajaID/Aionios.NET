<?php

namespace App\Console\Commands;

use App\Services\IsolationService;
use Illuminate\Console\Command;

class AutoIsolateCommand extends Command
{
    protected $signature = 'isp:auto-isolate';
    protected $description = 'Auto-isolate overdue delinquent customers (Runs on 23rd at 01:00 WIB)';

    public function handle(IsolationService $isolationService): int
    {
        $this->info("Menjalankan isolir otomatis untuk pelanggan yang menunggak...");

        $result = $isolationService->processAutoIsolation();

        $this->info("Selesai! Pelanggan diisolir: {$result['isolated_count']}");
        return self::SUCCESS;
    }
}
