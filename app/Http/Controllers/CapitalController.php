<?php

namespace App\Http\Controllers;

use App\Models\CapitalTransaction;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Services\AccountingService;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CapitalController extends Controller
{
    public function index(): Response
    {
        $transactions = CapitalTransaction::with(['chartOfAccount', 'cashBankAccount', 'creator'])
            ->latest()
            ->paginate(15);
        $equityCoas = ChartOfAccount::where('type', 'equity')->where('is_active', true)->get();
        $cashBankAccounts = CashBankAccount::where('is_active', true)->get();

        return Inertia::render('Capital/Index', [
            'transactions' => $transactions,
            'equity_coas' => $equityCoas,
            'cash_bank_accounts' => $cashBankAccounts,
        ]);
    }

    public function store(Request $request, AccountingService $accountingService): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'type' => 'required|in:deposit,additional,drawing',
            'chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'cash_bank_account_id' => 'required|exists:cash_bank_accounts,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $accountingService) {
            $date = Carbon::parse($validated['date']);
            $count = CapitalTransaction::whereDate('date', $date->toDateString())->count() + 1;
            $txNumber = 'CAP-' . $date->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $cap = CapitalTransaction::create([
                'transaction_number' => $txNumber,
                'date' => $date,
                'type' => $validated['type'],
                'chart_of_account_id' => $validated['chart_of_account_id'],
                'cash_bank_account_id' => $validated['cash_bank_account_id'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'created_by' => Auth::id(),
            ]);

            $cb = $cap->cashBankAccount;

            if ($validated['type'] === 'drawing') {
                // Pengambilan Prive: Debit Prive, Kredit Kas/Bank
                $cb->decrement('current_balance', $cap->amount);
                $lines = [
                    ['chart_of_account_id' => $cap->chart_of_account_id, 'debit' => $cap->amount, 'credit' => 0, 'memo' => $cap->description],
                    ['chart_of_account_id' => $cb->chart_of_account_id, 'debit' => 0, 'credit' => $cap->amount, 'memo' => "Penarikan dari {$cb->name}"],
                ];
            } else {
                // Setoran / Penambahan Modal: Debit Kas/Bank, Kredit Modal
                $cb->increment('current_balance', $cap->amount);
                $lines = [
                    ['chart_of_account_id' => $cb->chart_of_account_id, 'debit' => $cap->amount, 'credit' => 0, 'memo' => "Penerimaan modal di {$cb->name}"],
                    ['chart_of_account_id' => $cap->chart_of_account_id, 'debit' => 0, 'credit' => $cap->amount, 'memo' => $cap->description],
                ];
            }

            $accountingService->createBalancedJournal(
                'capital',
                $cap->id,
                $date,
                "Transaksi modal #{$cap->transaction_number}: {$cap->description}",
                $lines
            );

            AuditService::log('create_capital_transaction', 'accounting', 'CapitalTransaction', $cap->id, null, $cap->toArray());
        });

        return back()->with('success', 'Transaksi modal berhasil dicatat dan jurnal otomatis telah terbentuk.');
    }
}
