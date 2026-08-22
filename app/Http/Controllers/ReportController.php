<?php

namespace App\Http\Controllers;

use App\Models\CapitalTransaction;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\OtherIncome;
use App\Models\Payment;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function incomeStatement(Request $request): Response
    {
        $startDate = $request->filled('start_date') ? $request->start_date : now()->startOfMonth()->toDateString();
        $endDate = $request->filled('end_date') ? $request->end_date : now()->endOfMonth()->toDateString();

        $data = $this->accountingService->getIncomeStatement($startDate, $endDate);

        return Inertia::render('Reports/IncomeStatement', [
            'report' => $data,
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate],
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        $asOfDate = $request->filled('as_of_date') ? $request->as_of_date : now()->toDateString();
        $data = $this->accountingService->getBalanceSheet($asOfDate);

        return Inertia::render('Reports/BalanceSheet', [
            'report' => $data,
            'filters' => ['as_of_date' => $asOfDate],
        ]);
    }

    public function cashFlow(Request $request): Response
    {
        $startDate = $request->filled('start_date') ? $request->start_date : now()->startOfMonth()->toDateString();
        $endDate = $request->filled('end_date') ? $request->end_date : now()->endOfMonth()->toDateString();

        // 1. Operating Cash Inflows
        $internetCashInflow = Payment::where('status', 'posted')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('net_amount');

        $otherCashInflow = OtherIncome::whereBetween('date', [$startDate, $endDate])->sum('amount');

        // 2. Operating Cash Outflows
        $approvedExpenses = Expense::where('status', 'approved')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $netOperatingCash = ((float) $internetCashInflow + (float) $otherCashInflow) - (float) $approvedExpenses;

        // 3. Financing Cash Movements
        $capitalInflow = CapitalTransaction::whereIn('type', ['deposit', 'additional'])
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $capitalDrawing = CapitalTransaction::where('type', 'drawing')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $netFinancingCash = (float) $capitalInflow - (float) $capitalDrawing;
        $netCashChange = $netOperatingCash + $netFinancingCash;

        return Inertia::render('Reports/CashFlow', [
            'report' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'operating' => [
                    'internet_inflow' => (float) $internetCashInflow,
                    'other_inflow' => (float) $otherCashInflow,
                    'total_inflow' => (float) ($internetCashInflow + $otherCashInflow),
                    'expense_outflow' => (float) $approvedExpenses,
                    'net_operating' => (float) $netOperatingCash,
                ],
                'financing' => [
                    'capital_deposit' => (float) $capitalInflow,
                    'drawings' => (float) $capitalDrawing,
                    'net_financing' => (float) $netFinancingCash,
                ],
                'net_cash_change' => (float) $netCashChange,
            ],
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate],
        ]);
    }

    public function equityChanges(Request $request): Response
    {
        $startDate = $request->filled('start_date') ? $request->start_date : now()->startOfYear()->toDateString();
        $endDate = $request->filled('end_date') ? $request->end_date : now()->toDateString();

        // Calculate opening capital
        $priorCapitals = CapitalTransaction::where('date', '<', $startDate)->get();
        $openingCapital = 82500000; // Starting base from migration
        foreach ($priorCapitals as $pc) {
            if ($pc->type === 'drawing') {
                $openingCapital -= (float) $pc->amount;
            } else {
                $openingCapital += (float) $pc->amount;
            }
        }

        // Current period additions / drawings
        $additions = (float) CapitalTransaction::whereIn('type', ['deposit', 'additional'])
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $drawings = (float) CapitalTransaction::where('type', 'drawing')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        // Net profit in period
        $incData = $this->accountingService->getIncomeStatement($startDate, $endDate);
        $periodProfit = $incData['net_profit'];

        $endingEquity = $openingCapital + $additions - $drawings + $periodProfit;

        return Inertia::render('Reports/EquityChanges', [
            'report' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'opening_capital' => (float) $openingCapital,
                'additions' => (float) $additions,
                'drawings' => (float) $drawings,
                'period_profit' => (float) $periodProfit,
                'ending_equity' => (float) $endingEquity,
            ],
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate],
        ]);
    }

    public function receivables(Request $request): Response
    {
        $query = Invoice::whereIn('status', ['unpaid', 'overdue'])
            ->with(['customer.package']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $invoices = $query->orderBy('due_date')->get();

        $rows = [];
        $totalOutstanding = 0;
        $agingBuckets = [
            'current' => 0, // not overdue yet
            '1_30' => 0,    // 1-30 days overdue
            '31_60' => 0,   // 31-60 days overdue
            'over_60' => 0, // > 60 days overdue
        ];

        $today = Carbon::today();

        foreach ($invoices as $inv) {
            $dueDate = Carbon::parse($inv->due_date);
            $daysOverdue = $today->greaterThan($dueDate) ? $today->diffInDays($dueDate) : 0;
            $amount = (float) $inv->total_amount;
            $totalOutstanding += $amount;

            if ($daysOverdue === 0) {
                $agingBuckets['current'] += $amount;
            } elseif ($daysOverdue <= 30) {
                $agingBuckets['1_30'] += $amount;
            } elseif ($daysOverdue <= 60) {
                $agingBuckets['31_60'] += $amount;
            } else {
                $agingBuckets['over_60'] += $amount;
            }

            $rows[] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_id' => $inv->customer->customer_id,
                'customer_name' => $inv->customer->name,
                'customer_status' => $inv->customer->status,
                'package_name' => $inv->customer->package?->name ?? '-',
                'period' => $inv->period,
                'due_date' => $inv->due_date->format('Y-m-d'),
                'amount' => $amount,
                'days_overdue' => $daysOverdue,
            ];
        }

        $customers = Customer::where('status', '!=', 'terminated')->get();

        return Inertia::render('Reports/Receivables', [
            'report' => [
                'as_of_date' => now()->toDateString(),
                'total_outstanding' => $totalOutstanding,
                'aging' => $agingBuckets,
                'rows' => $rows,
            ],
            'customers' => $customers,
            'filters' => $request->only('customer_id'),
        ]);
    }

    public function revenue(Request $request): Response
    {
        $startDate = $request->filled('start_date') ? $request->start_date : now()->startOfMonth()->toDateString();
        $endDate = $request->filled('end_date') ? $request->end_date : now()->endOfMonth()->toDateString();

        $payments = Payment::where('status', 'posted')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->with(['customer', 'cashBankAccount'])
            ->latest('payment_date')
            ->get();

        $otherIncomes = OtherIncome::whereBetween('date', [$startDate, $endDate])
            ->with(['chartOfAccount', 'cashBankAccount'])
            ->latest('date')
            ->get();

        $grossInternet = (float) $payments->sum('gross_amount');
        $totalMdr = (float) $payments->sum('mdr_fee');
        $netInternet = (float) $payments->sum('net_amount');
        $totalOther = (float) $otherIncomes->sum('amount');
        $totalGrossRevenue = $grossInternet + $totalOther;
        $totalNetRevenue = $netInternet + $totalOther;

        return Inertia::render('Reports/Revenue', [
            'report' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'gross_internet' => $grossInternet,
                'total_mdr' => $totalMdr,
                'net_internet' => $netInternet,
                'total_other' => $totalOther,
                'total_gross' => $totalGrossRevenue,
                'total_net' => $totalNetRevenue,
                'payments' => $payments,
                'other_incomes' => $otherIncomes,
            ],
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate],
        ]);
    }
}
