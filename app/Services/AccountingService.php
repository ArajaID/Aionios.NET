<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\AccountMapping;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    public function isPeriodClosed(string $period): bool
    {
        $periodRecord = AccountingPeriod::where('period', $period)->first();
        return $periodRecord && $periodRecord->isClosed();
    }

    public function assertPeriodOpen(Carbon $date): void
    {
        $period = $date->format('Y-m');
        if ($this->isPeriodClosed($period)) {
            throw new Exception("Periode akuntansi {$period} telah ditutup (Locked). Transaksi tidak dapat dicatat pada periode ini.");
        }
    }

    public function getMappedAccount(string $purpose): ChartOfAccount
    {
        $mapping = AccountMapping::where('purpose', $purpose)->with('chartOfAccount')->first();
        if (!$mapping || !$mapping->chartOfAccount) {
            // Fallback lookup by purpose or code
            $account = match ($purpose) {
                'cash_default' => ChartOfAccount::where('code', '1110')->first(),
                'bank_default' => ChartOfAccount::where('code', '1120')->first(),
                'ar_internet' => ChartOfAccount::where('code', '1210')->first(),
                'revenue_internet' => ChartOfAccount::where('code', '4110')->first(),
                'revenue_other' => ChartOfAccount::where('code', '4210')->first(),
                'expense_mdr' => ChartOfAccount::where('code', '5170')->first(),
                'equity_capital' => ChartOfAccount::where('code', '3110')->first(),
                'equity_retained_earnings' => ChartOfAccount::where('code', '3310')->first(),
                'equity_drawing' => ChartOfAccount::where('code', '3210')->first(),
                default => null,
            };

            if (!$account) {
                throw new Exception("Pemetaan akun untuk '{$purpose}' belum dikonfigurasi.");
            }
            return $account;
        }
        return $mapping->chartOfAccount;
    }

    public function createBalancedJournal(
        string $referenceType,
        ?int $referenceId,
        Carbon $date,
        string $description,
        array $lines
    ): JournalEntry {
        $this->assertPeriodOpen($date);

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $totalDebit += (float) ($line['debit'] ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new Exception("Jurnal tidak seimbang (Unbalanced)! Total Debit: Rp " . number_format($totalDebit, 2) . ", Total Credit: Rp " . number_format($totalCredit, 2));
        }

        $count = JournalEntry::whereDate('date', $date->toDateString())->count() + 1;
        $entryNumber = 'JRN-' . $date->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $entry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'date' => $date,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'status' => 'posted',
            'created_by' => Auth::id(),
            'is_balanced' => true,
        ]);

        foreach ($lines as $line) {
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'chart_of_account_id' => $line['chart_of_account_id'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'memo' => $line['memo'] ?? null,
            ]);
        }

        return $entry;
    }

    public function postPaymentJournal(Payment $payment): JournalEntry
    {
        $cashBankCoaId = $payment->cashBankAccount->chart_of_account_id;
        $revenueAccount = $this->getMappedAccount('revenue_internet');
        $mdrAccount = $this->getMappedAccount('expense_mdr');

        $lines = [];

        // Debit Kas/Bank (Net amount)
        $lines[] = [
            'chart_of_account_id' => $cashBankCoaId,
            'debit' => $payment->net_amount,
            'credit' => 0,
            'memo' => "Penerimaan kas/bank payment #{$payment->payment_number}",
        ];

        // Debit MDR Expense if QRIS with MDR fee > 0
        if ($payment->mdr_fee > 0) {
            $lines[] = [
                'chart_of_account_id' => $mdrAccount->id,
                'debit' => $payment->mdr_fee,
                'credit' => 0,
                'memo' => "Biaya MDR QRIS {$payment->mdr_percentage}%",
            ];
        }

        // Kredit Pendapatan Internet (Gross amount)
        $lines[] = [
            'chart_of_account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => $payment->gross_amount,
            'memo' => "Pendapatan Internet pelanggan {$payment->customer->name} ({$payment->customer->customer_id})",
        ];

        // Update Cash Bank balance
        $payment->cashBankAccount->increment('current_balance', $payment->net_amount);

        return $this->createBalancedJournal(
            'payment',
            $payment->id,
            Carbon::parse($payment->payment_date),
            "Pembayaran tagihan pelanggan {$payment->customer->name} ({$payment->payment_method})",
            $lines
        );
    }

    public function postReversalJournal(Payment $payment, string $reason): JournalEntry
    {
        $cashBankCoaId = $payment->cashBankAccount->chart_of_account_id;
        $revenueAccount = $this->getMappedAccount('revenue_internet');
        $mdrAccount = $this->getMappedAccount('expense_mdr');

        $lines = [];

        // Debit Pendapatan Internet (Gross amount)
        $lines[] = [
            'chart_of_account_id' => $revenueAccount->id,
            'debit' => $payment->gross_amount,
            'credit' => 0,
            'memo' => "Reversal Pendapatan Internet #{$payment->payment_number}: {$reason}",
        ];

        // Kredit MDR Expense if any
        if ($payment->mdr_fee > 0) {
            $lines[] = [
                'chart_of_account_id' => $mdrAccount->id,
                'debit' => 0,
                'credit' => $payment->mdr_fee,
                'memo' => "Reversal Biaya MDR QRIS",
            ];
        }

        // Kredit Kas/Bank (Net amount)
        $lines[] = [
            'chart_of_account_id' => $cashBankCoaId,
            'debit' => 0,
            'credit' => $payment->net_amount,
            'memo' => "Pengurangan kas/bank reversal #{$payment->payment_number}",
        ];

        // Update Cash Bank balance
        $payment->cashBankAccount->decrement('current_balance', $payment->net_amount);

        return $this->createBalancedJournal(
            'reversal',
            $payment->id,
            now(),
            "Reversal pembayaran #{$payment->payment_number} ({$reason})",
            $lines
        );
    }

    public function getGeneralLedger(?int $coaId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $accountsQuery = ChartOfAccount::where('is_active', true);
        if ($coaId) {
            $accountsQuery->where('id', $coaId);
        }
        $accounts = $accountsQuery->orderBy('code')->get();

        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        $ledgerData = [];

        foreach ($accounts as $account) {
            // Calculate opening balance before startDate
            $priorLines = JournalLine::where('chart_of_account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate) {
                    $q->where('status', 'posted')->where('date', '<', $startDate);
                })->get();

            $priorDebit = $priorLines->sum('debit');
            $priorCredit = $priorLines->sum('credit');
            $openingBalance = $account->normal_balance === 'debit' ? ($priorDebit - $priorCredit) : ($priorCredit - $priorDebit);

            // Current period lines
            $currentLines = JournalLine::where('chart_of_account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->where('status', 'posted')->whereBetween('date', [$startDate, $endDate]);
                })
                ->with('journalEntry')
                ->get();

            $runningBalance = $openingBalance;
            $formattedLines = [];

            foreach ($currentLines as $line) {
                if ($account->normal_balance === 'debit') {
                    $runningBalance += ((float) $line->debit - (float) $line->credit);
                } else {
                    $runningBalance += ((float) $line->credit - (float) $line->debit);
                }

                $formattedLines[] = [
                    'id' => $line->id,
                    'date' => $line->journalEntry->date->format('Y-m-d'),
                    'entry_number' => $line->journalEntry->entry_number,
                    'description' => $line->journalEntry->description,
                    'memo' => $line->memo,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'running_balance' => (float) $runningBalance,
                ];
            }

            $totalDebit = $currentLines->sum('debit');
            $totalCredit = $currentLines->sum('credit');
            $endingBalance = $runningBalance;

            $ledgerData[] = [
                'account' => $account,
                'opening_balance' => $openingBalance,
                'total_debit' => (float) $totalDebit,
                'total_credit' => (float) $totalCredit,
                'ending_balance' => (float) $endingBalance,
                'lines' => $formattedLines,
            ];
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'accounts' => $ledgerData,
        ];
    }

    public function getTrialBalance(?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();
        $accounts = ChartOfAccount::where('is_active', true)->orderBy('code')->get();

        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $lines = JournalLine::where('chart_of_account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('status', 'posted')->where('date', '<=', $asOfDate);
                })->get();

            $sumDebit = (float) $lines->sum('debit');
            $sumCredit = (float) $lines->sum('credit');
            $net = $sumDebit - $sumCredit;

            $debitBalance = 0;
            $creditBalance = 0;

            if ($account->normal_balance === 'debit') {
                if ($net >= 0) {
                    $debitBalance = $net;
                } else {
                    $creditBalance = abs($net);
                }
            } else {
                if ($net <= 0) {
                    $creditBalance = abs($net);
                } else {
                    $debitBalance = $net;
                }
            }

            if ($debitBalance > 0 || $creditBalance > 0) {
                $rows[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'debit' => $debitBalance,
                    'credit' => $creditBalance,
                ];
                $totalDebit += $debitBalance;
                $totalCredit += $creditBalance;
            }
        }

        return [
            'as_of_date' => $asOfDate,
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => round($totalDebit, 2) === round($totalCredit, 2),
        ];
    }

    public function getIncomeStatement(string $startDate, string $endDate): array
    {
        $revenueAccounts = ChartOfAccount::where('type', 'revenue')->where('is_active', true)->orderBy('code')->get();
        $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('is_active', true)->orderBy('code')->get();

        $revenues = [];
        $totalRevenue = 0;

        foreach ($revenueAccounts as $acc) {
            $lines = JournalLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->where('status', 'posted')->whereBetween('date', [$startDate, $endDate]);
                })->get();

            $amount = (float) ($lines->sum('credit') - $lines->sum('debit'));
            if ($amount != 0) {
                $revenues[] = [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'amount' => $amount,
                ];
                $totalRevenue += $amount;
            }
        }

        $expenses = [];
        $totalExpense = 0;

        foreach ($expenseAccounts as $acc) {
            $lines = JournalLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->where('status', 'posted')->whereBetween('date', [$startDate, $endDate]);
                })->get();

            $amount = (float) ($lines->sum('debit') - $lines->sum('credit'));
            if ($amount != 0) {
                $expenses[] = [
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'amount' => $amount,
                ];
                $totalExpense += $amount;
            }
        }

        $netProfit = $totalRevenue - $totalExpense;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'revenues' => $revenues,
            'total_revenue' => $totalRevenue,
            'expenses' => $expenses,
            'total_expense' => $totalExpense,
            'net_profit' => $netProfit,
        ];
    }

    public function getBalanceSheet(string $asOfDate): array
    {
        $assetAccounts = ChartOfAccount::where('type', 'asset')->where('is_active', true)->orderBy('code')->get();
        $liabilityAccounts = ChartOfAccount::where('type', 'liability')->where('is_active', true)->orderBy('code')->get();
        $equityAccounts = ChartOfAccount::where('type', 'equity')->where('is_active', true)->orderBy('code')->get();

        $assets = [];
        $totalAssets = 0;

        foreach ($assetAccounts as $acc) {
            $lines = JournalLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('status', 'posted')->where('date', '<=', $asOfDate);
                })->get();
            $amount = (float) ($lines->sum('debit') - $lines->sum('credit'));
            if ($amount != 0) {
                $assets[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => $amount];
                $totalAssets += $amount;
            }
        }

        $liabilities = [];
        $totalLiabilities = 0;

        foreach ($liabilityAccounts as $acc) {
            $lines = JournalLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('status', 'posted')->where('date', '<=', $asOfDate);
                })->get();
            $amount = (float) ($lines->sum('credit') - $lines->sum('debit'));
            if ($amount != 0) {
                $liabilities[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => $amount];
                $totalLiabilities += $amount;
            }
        }

        $equity = [];
        $totalEquity = 0;

        foreach ($equityAccounts as $acc) {
            $lines = JournalLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                    $q->where('status', 'posted')->where('date', '<=', $asOfDate);
                })->get();
            $amount = (float) ($lines->sum('credit') - $lines->sum('debit'));
            if ($amount != 0) {
                $equity[] = ['code' => $acc->code, 'name' => $acc->name, 'amount' => $amount];
                $totalEquity += $amount;
            }
        }

        // Add Retained Earnings from all revenues - expenses
        $revLines = JournalLine::whereHas('chartOfAccount', fn($q) => $q->where('type', 'revenue'))
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted')->where('date', '<=', $asOfDate))->get();
        $expLines = JournalLine::whereHas('chartOfAccount', fn($q) => $q->where('type', 'expense'))
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted')->where('date', '<=', $asOfDate))->get();

        $cumulativeProfit = (float) (($revLines->sum('credit') - $revLines->sum('debit')) - ($expLines->sum('debit') - $expLines->sum('credit')));
        if ($cumulativeProfit != 0) {
            $equity[] = [
                'code' => '3310',
                'name' => 'Laba Periode Berjalan',
                'amount' => $cumulativeProfit,
            ];
            $totalEquity += $cumulativeProfit;
        }

        return [
            'as_of_date' => $asOfDate,
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equity,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabilities + $totalEquity,
            'is_balanced' => round($totalAssets, 2) === round($totalLiabilities + $totalEquity, 2),
        ];
    }
}
