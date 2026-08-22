<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $query = JournalEntry::with(['lines.chartOfAccount', 'creator']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('entry_number', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('reference_type', $request->type);
        }

        $journals = $query->latest('date')->latest('id')->paginate(15)->withQueryString();
        $coas = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

        return Inertia::render('Accounting/Journals', [
            'journals' => $journals,
            'coas' => $coas,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function storeManual(Request $request, AccountingService $accountingService): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'lines' => 'required|array|min:2',
            'lines.*.chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.memo' => 'nullable|string|max:255',
        ]);

        try {
            $journal = $accountingService->createBalancedJournal(
                'manual',
                null,
                Carbon::parse($validated['date']),
                $validated['description'],
                $validated['lines']
            );

            AuditService::log('create_manual_journal', 'accounting', 'JournalEntry', $journal->id, null, ['description' => $validated['description']]);

            return back()->with('success', "Jurnal manual {$journal->entry_number} berhasil diposting.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memposting jurnal: ' . $e->getMessage());
        }
    }

    public function ledger(Request $request, AccountingService $accountingService): Response
    {
        $coaId = $request->filled('coa_id') ? (int) $request->coa_id : null;
        $startDate = $request->filled('start_date') ? $request->start_date : now()->startOfMonth()->toDateString();
        $endDate = $request->filled('end_date') ? $request->end_date : now()->endOfMonth()->toDateString();

        $ledgerData = $accountingService->getGeneralLedger($coaId, $startDate, $endDate);
        $coas = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

        return Inertia::render('Accounting/Ledger', [
            'ledger' => $ledgerData,
            'coas' => $coas,
            'filters' => [
                'coa_id' => $coaId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function trialBalance(Request $request, AccountingService $accountingService): Response
    {
        $asOfDate = $request->filled('as_of_date') ? $request->as_of_date : now()->toDateString();
        $tbData = $accountingService->getTrialBalance($asOfDate);

        return Inertia::render('Accounting/TrialBalance', [
            'trial_balance' => $tbData,
            'as_of_date' => $asOfDate,
        ]);
    }
}
