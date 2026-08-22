<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\CashBankAccount;
use App\Models\Customer;
use App\Models\CustomerPromotion;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\MikrotikRouter;
use App\Models\NetworkJob;
use App\Models\Ont;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PppAccount;
use App\Models\ReversalRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentMonth = now()->format('Y-m');
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        // 1. Financial Stats
        $currentMonthInvoices = Invoice::where('period', $currentMonth)->get();
        $totalBilled = (float) $currentMonthInvoices->sum('total_amount');
        $totalPaidBilled = (float) $currentMonthInvoices->sum('paid_amount');
        $collectionRate = $totalBilled > 0 ? round(($totalPaidBilled / $totalBilled) * 100, 1) : 0;

        $totalReceivables = (float) Invoice::whereIn('status', ['unpaid', 'overdue'])->sum('total_amount');
        $totalCashBank = (float) CashBankAccount::where('is_active', true)->sum('current_balance');

        $monthPayments = Payment::where('status', 'posted')
            ->whereBetween('payment_date', [$startOfMonth, $endOfMonth])
            ->get();
        $grossRevenue = (float) $monthPayments->sum('gross_amount');
        $totalMdr = (float) $monthPayments->sum('mdr_fee');
        $netRevenue = (float) $monthPayments->sum('net_amount');

        $monthExpenses = Expense::where('status', 'approved')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $netProfit = $netRevenue - (float) $monthExpenses;

        // 2. Customer Stats
        $customerCounts = [
            'total' => Customer::count(),
            'active' => Customer::where('status', 'active')->count(),
            'isolated' => Customer::where('status', 'isolated')->count(),
            'terminated' => Customer::where('status', 'terminated')->count(),
            'new_this_month' => Customer::whereBetween('activated_at', [$startOfMonth, $endOfMonth])->count(),
        ];

        // 3. Network Stats
        $router = MikrotikRouter::where('is_active', true)->first();
        $pppCounts = [
            'total' => PppAccount::count(),
            'connected' => PppAccount::where('status', 'connected')->count(),
            'isolated' => PppAccount::where('status', 'isolated')->count(),
            'disabled' => PppAccount::where('status', 'disabled')->count(),
        ];

        $pendingSyncCount = NetworkJob::where('status', 'pending')->count();

        // 4. ONT Stats
        $ontCounts = [
            'total' => Ont::count(),
            'available' => Ont::where('status', 'available')->count(),
            'installed' => Ont::where('status', 'installed')->count(),
            'returned' => Ont::where('status', 'returned')->count(),
            'damaged' => Ont::where('status', 'damaged')->count(),
        ];

        // 5. Approvals Pending
        $pendingExpenses = Expense::where('status', 'pending')->count();
        $pendingReversals = ReversalRequest::where('status', 'pending')->count();

        // 6. Cash Bank Breakdown
        $cashBankAccounts = CashBankAccount::where('is_active', true)->get();

        // 7. Recent Invoices & Payments
        $recentPayments = Payment::with(['customer', 'cashBankAccount'])
            ->latest()
            ->take(5)
            ->get();

        $recentInvoices = Invoice::with('customer')
            ->latest()
            ->take(5)
            ->get();

        // 8. Package Distribution
        $packageStats = Package::withCount('customers')->get();

        // 9. Expiring Promos
        $expiringPromos = CustomerPromotion::where('status', 'active')
            ->where('end_date', '<=', now()->addDays(7))
            ->with(['customer', 'promotion'])
            ->get();

        // 10. Monthly Revenue Trend (Last 6 Months)
        $revenueTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $p = $month->format('Y-m');
            $rev = Payment::where('status', 'posted')
                ->whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->sum('gross_amount');
            $revenueTrend[] = [
                'period' => $month->translatedFormat('M Y'),
                'revenue' => (float) $rev,
            ];
        }

        $props = [
            'period' => $currentMonth,
            'kpis' => [
                'gross_revenue' => $grossRevenue,
                'total_mdr' => $totalMdr,
                'net_revenue' => $netRevenue,
                'month_expenses' => (float) $monthExpenses,
                'net_profit' => $netProfit,
                'total_receivables' => $totalReceivables,
                'total_cash_bank' => $totalCashBank,
                'total_billed' => $totalBilled,
                'total_paid_billed' => $totalPaidBilled,
                'collection_rate' => $collectionRate,
            ],
            'customer_counts' => $customerCounts,
            'network_stats' => [
                'router_status' => $router?->status ?? 'offline',
                'router_name' => $router?->name ?? 'MikroTik Gateway',
                'ppp_counts' => $pppCounts,
                'pending_sync_count' => $pendingSyncCount,
            ],
            'ont_counts' => $ontCounts,
            'approvals' => [
                'pending_expenses' => $pendingExpenses,
                'pending_reversals' => $pendingReversals,
                'total_pending' => $pendingExpenses + $pendingReversals,
            ],
            'cash_bank_accounts' => $cashBankAccounts,
            'recent_payments' => $recentPayments,
            'recent_invoices' => $recentInvoices,
            'package_stats' => $packageStats,
            'expiring_promos' => $expiringPromos,
            'revenue_trend' => $revenueTrend,
        ];

        if ($user->role === 'owner') {
            return Inertia::render('Dashboard/OwnerDashboard', $props);
        } elseif ($user->role === 'admin_keuangan') {
            return Inertia::render('Dashboard/FinanceDashboard', $props);
        } else {
            return Inertia::render('Dashboard/NetworkDashboard', $props);
        }
    }
}
