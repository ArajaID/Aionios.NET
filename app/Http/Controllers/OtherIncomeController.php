<?php

namespace App\Http\Controllers;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\OtherIncome;
use App\Services\AccountingService;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OtherIncomeController extends Controller
{
    public function index(): Response
    {
        $incomes = OtherIncome::with(['chartOfAccount', 'cashBankAccount', 'creator'])
            ->latest()
            ->paginate(15);
        $revenueCoas = ChartOfAccount::where('type', 'revenue')->where('is_active', true)->get();
        $cashBankAccounts = CashBankAccount::where('is_active', true)->get();

        return Inertia::render('OtherIncome/Index', [
            'incomes' => $incomes,
            'revenue_coas' => $revenueCoas,
            'cash_bank_accounts' => $cashBankAccounts,
        ]);
    }

    public function store(Request $request, AccountingService $accountingService): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'cash_bank_account_id' => 'required|exists:cash_bank_accounts,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($validated, $accountingService) {
            $date = Carbon::parse($validated['date']);
            $count = OtherIncome::whereDate('date', $date->toDateString())->count() + 1;
            $incomeNumber = 'INC-' . $date->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $income = OtherIncome::create([
                'income_number' => $incomeNumber,
                'date' => $date,
                'chart_of_account_id' => $validated['chart_of_account_id'],
                'cash_bank_account_id' => $validated['cash_bank_account_id'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'reference' => $validated['reference'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $cb = $income->cashBankAccount;
            $cb->increment('current_balance', $income->amount);

            // Post balanced journal
            $accountingService->createBalancedJournal(
                'other_income',
                $income->id,
                $date,
                "Pemasukan lain #{$income->income_number}: {$income->description}",
                [
                    [
                        'chart_of_account_id' => $cb->chart_of_account_id,
                        'debit' => $income->amount,
                        'credit' => 0,
                        'memo' => "Penerimaan {$cb->name}",
                    ],
                    [
                        'chart_of_account_id' => $income->chart_of_account_id,
                        'debit' => 0,
                        'credit' => $income->amount,
                        'memo' => $income->description,
                    ],
                ]
            );

            AuditService::log('create_other_income', 'accounting', 'OtherIncome', $income->id, null, $income->toArray());
        });

        return back()->with('success', 'Pemasukan lain berhasil dicatat dan jurnal otomatis telah terbentuk.');
    }
}
