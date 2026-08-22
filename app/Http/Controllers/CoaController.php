<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\OpeningBalance;
use App\Services\AccountingService;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CoaController extends Controller
{
    public function index(): Response
    {
        $coas = ChartOfAccount::orderBy('code')->get();
        return Inertia::render('Accounting/Coa', [
            'coas' => $coas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:chart_of_accounts,code|max:20',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'category' => 'required|string|max:100',
            'normal_balance' => 'required|in:debit,credit',
        ]);

        $coa = ChartOfAccount::create($validated);
        AuditService::log('create_coa', 'accounting', 'ChartOfAccount', $coa->id, null, $coa->toArray());

        return back()->with('success', "Akun COA {$coa->code} - {$coa->name} berhasil ditambahkan.");
    }

    public function openingBalance(): Response
    {
        $coas = ChartOfAccount::where('is_active', true)->orderBy('code')->get();
        $history = OpeningBalance::with('poster')->latest()->get();

        return Inertia::render('Accounting/OpeningBalance', [
            'coas' => $coas,
            'history' => $history,
        ]);
    }

    public function postOpeningBalance(Request $request, AccountingService $accountingService): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
        ]);

        $totalDebit = 0;
        $totalCredit = 0;
        $journalLines = [];

        foreach ($validated['lines'] as $line) {
            $debit = (float) $line['debit'];
            $credit = (float) $line['credit'];

            if ($debit > 0 || $credit > 0) {
                $totalDebit += $debit;
                $totalCredit += $credit;

                $journalLines[] = [
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'memo' => 'Saldo awal migrasi',
                ];
            }
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return back()->with('error', "Saldo awal tidak seimbang (Unbalanced)! Total Debit: Rp " . number_format($totalDebit, 0, ',', '.') . " != Total Kredit: Rp " . number_format($totalCredit, 0, ',', '.'));
        }

        DB::transaction(function () use ($validated, $totalDebit, $totalCredit, $journalLines, $accountingService) {
            $date = Carbon::parse($validated['date']);

            $ob = OpeningBalance::create([
                'date' => $date,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_by' => Auth::id(),
                'notes' => $validated['notes'] ?? 'Posting Saldo Awal',
            ]);

            $accountingService->createBalancedJournal(
                'opening_balance',
                $ob->id,
                $date,
                "Saldo Awal Pembukuan: " . ($validated['notes'] ?? 'Migrasi Sistem'),
                $journalLines
            );

            AuditService::log('post_opening_balance', 'accounting', 'OpeningBalance', $ob->id, null, ['total' => $totalDebit]);
        });

        return redirect()->route('accounting.coa')->with('success', 'Saldo awal berhasil diposting dan jurnal pembukuan telah dibuat.');
    }
}
