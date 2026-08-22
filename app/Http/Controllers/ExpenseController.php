<?php

namespace App\Http\Controllers;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Notification;
use App\Services\AccountingService;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Expense::with(['chartOfAccount', 'cashBankAccount', 'submitter', 'approver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('expense_number', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $expenses = $query->latest()->paginate(12)->withQueryString();
        $expenseCoas = ChartOfAccount::where('type', 'expense')->where('is_active', true)->get();
        $cashBankAccounts = CashBankAccount::where('is_active', true)->get();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'expense_coas' => $expenseCoas,
            'cash_bank_accounts' => $cashBankAccounts,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'chart_of_account_id' => 'required|exists:chart_of_accounts,id',
            'cash_bank_account_id' => 'required|exists:cash_bank_accounts,id',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $date = Carbon::parse($validated['date']);
        $count = Expense::whereDate('date', $date->toDateString())->count() + 1;
        $expenseNumber = 'EXP-' . $date->format('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        $expense = Expense::create([
            'expense_number' => $expenseNumber,
            'date' => $date,
            'chart_of_account_id' => $validated['chart_of_account_id'],
            'cash_bank_account_id' => $validated['cash_bank_account_id'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'submitted_by' => Auth::id(),
        ]);

        Notification::create([
            'role' => 'owner',
            'type' => 'warning',
            'title' => 'Pengajuan Pengeluaran Baru',
            'message' => "Pengajuan {$expense->expense_number} sebesar Rp " . number_format($expense->amount, 0, ',', '.') . " menunggu persetujuan Anda.",
            'link' => '/approvals',
        ]);

        AuditService::log('submit_expense', 'expenses', 'Expense', $expense->id, null, $expense->toArray());

        return back()->with('success', "Pengajuan pengeluaran {$expense->expense_number} berhasil diajukan dan menunggu persetujuan Owner.");
    }

    public function approve(Expense $expense, AccountingService $accountingService): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak menyetujui pengeluaran.');
        }

        if ($expense->status !== 'pending') {
            return back()->with('error', 'Pengeluaran ini tidak dalam status menunggu persetujuan.');
        }

        DB::transaction(function () use ($expense, $accountingService) {
            $expense->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Deduct cash/bank balance
            $cb = $expense->cashBankAccount;
            $cb->decrement('current_balance', $expense->amount);

            // Post balanced journal
            $accountingService->createBalancedJournal(
                'expense',
                $expense->id,
                Carbon::parse($expense->date),
                "Beban operasional #{$expense->expense_number}: {$expense->description}",
                [
                    [
                        'chart_of_account_id' => $expense->chart_of_account_id,
                        'debit' => $expense->amount,
                        'credit' => 0,
                        'memo' => $expense->description,
                    ],
                    [
                        'chart_of_account_id' => $cb->chart_of_account_id,
                        'debit' => 0,
                        'credit' => $expense->amount,
                        'memo' => "Pengeluaran kas/bank {$cb->name}",
                    ],
                ]
            );

            Notification::create([
                'user_id' => $expense->submitted_by,
                'type' => 'success',
                'title' => 'Pengeluaran Disetujui',
                'message' => "Pengajuan pengeluaran {$expense->expense_number} sebesar Rp " . number_format($expense->amount, 0, ',', '.') . " telah disetujui Owner.",
                'link' => '/expenses',
            ]);

            AuditService::log('approve_expense', 'expenses', 'Expense', $expense->id);
        });

        return back()->with('success', "Pengeluaran {$expense->expense_number} berhasil disetujui dan jurnal otomatis telah diposting.");
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        if (!Auth::user()->isOwner()) {
            abort(403, 'Hanya Owner yang berhak menolak pengeluaran.');
        }

        $request->validate(['rejection_reason' => 'required|string|min:3|max:500']);

        $expense->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        Notification::create([
            'user_id' => $expense->submitted_by,
            'type' => 'danger',
            'title' => 'Pengeluaran Ditolak',
            'message' => "Pengajuan pengeluaran {$expense->expense_number} ditolak Owner. Alasan: {$request->rejection_reason}",
            'link' => '/expenses',
        ]);

        AuditService::log('reject_expense', 'expenses', 'Expense', $expense->id, null, ['reason' => $request->rejection_reason]);

        return back()->with('success', "Pengeluaran {$expense->expense_number} telah ditolak.");
    }
}
