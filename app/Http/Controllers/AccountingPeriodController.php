<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountingPeriodController extends Controller
{
    public function index(): Response
    {
        $periods = AccountingPeriod::with(['closer', 'reopener'])->latest('period')->get();
        return Inertia::render('Accounting/Periods', [
            'periods' => $periods,
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak menutup periode akuntansi.');
        }

        $validated = $request->validate([
            'period' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $period = AccountingPeriod::updateOrCreate(
            ['period' => $validated['period']],
            [
                'status' => 'closed',
                'closed_by' => Auth::id(),
                'closed_at' => now(),
            ]
        );

        AuditService::log('close_accounting_period', 'accounting', 'AccountingPeriod', $period->id, null, ['period' => $period->period]);

        return back()->with('success', "Periode akuntansi {$period->period} berhasil dikunci (Closed). Tidak ada transaksi baru yang dapat dicatat pada periode ini.");
    }

    public function reopen(Request $request, AccountingPeriod $accountingPeriod): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak membuka kembali periode akuntansi.');
        }

        $request->validate([
            'reopen_reason' => 'required|string|min:5|max:500',
        ]);

        $accountingPeriod->update([
            'status' => 'open',
            'reopened_by' => Auth::id(),
            'reopened_at' => now(),
            'reopen_reason' => $request->reopen_reason,
        ]);

        AuditService::log('reopen_accounting_period', 'accounting', 'AccountingPeriod', $accountingPeriod->id, null, [
            'period' => $accountingPeriod->period,
            'reason' => $request->reopen_reason,
        ]);

        return back()->with('success', "Periode akuntansi {$accountingPeriod->period} berhasil dibuka kembali (Re-opened).");
    }
}
