<?php

use Illuminate\Support\Facades\Schedule;

// Regular Monthly Billing: 1st of month at 00:01 WIB
Schedule::command('isp:generate-invoices')->monthlyOn(1, '00:01')->timezone('Asia/Jakarta');

// Auto-Isolation: 23rd of month at 01:00 WIB
Schedule::command('isp:auto-isolate')->monthlyOn(23, '01:00')->timezone('Asia/Jakarta');

// Daily check for expired promotions and network queue retries
Schedule::command('isp:check-promotions')->dailyAt('00:30')->timezone('Asia/Jakarta');
Schedule::command('isp:process-network-jobs')->everyFiveMinutes();
