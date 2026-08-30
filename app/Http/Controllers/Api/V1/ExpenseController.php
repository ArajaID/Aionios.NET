<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RejectExpenseRequest;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Resources\Api\V1\ExpenseResource;
use App\Models\CashBankAccount;
use App\Models\Expense;
use App\Models\Notification;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @tags Pengeluaran (Expenses)
 */
class ExpenseController extends Controller
{
    /**
     * Daftar Pengeluaran Kas
     *
     * Menampilkan riwayat transaksi beban/pengeluaran kas operasional dengan filter pencarian nomor/keterangan, filter status (draft, pending, approved, rejected), sorting, dan paginasi data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,pending,pending_approval,approved,rejected'],
            'sort' => ['nullable', 'in:created_at,-created_at,date,-date,amount,-amount,status,-status'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Expense::with(['chartOfAccount', 'cashBankAccount', 'submitter', 'approver']);
        if ($search = ($validated['search'] ?? null)) {
            $query->where(fn ($builder) => $builder
                ->where('expense_number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status'] === 'pending_approval' ? 'pending' : $validated['status']);
        }
        $sort = $validated['sort'] ?? '-created_at';
        $paginator = $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->paginate($validated['per_page'] ?? 20);

        return ApiResponse::paginated($paginator, ExpenseResource::collection($paginator->getCollection())->resolve());
    }

    /**
     * Detail Pengeluaran Kas
     *
     * Menampilkan rincian lengkap transaksi pengeluaran kas, termasuk akun beban COA, akun kas/bank asal dana, petugas pembuat, status persetujuan, dan lampiran bukti transaksi.
     *
     * @param Expense $expense
     * @return JsonResponse
     */
    public function show(Expense $expense): JsonResponse
    {
        return ApiResponse::success((new ExpenseResource(
            $expense->load(['chartOfAccount', 'cashBankAccount', 'submitter', 'approver'])
        ))->resolve());
    }

    /**
     * Buat Draft Pengeluaran Kas
     *
     * Mencatat transaksi pengeluaran kas baru dengan status awal 'draft'. Mendukung unggah berkas bukti transaksi (nota, kuitansi, struk).
     *
     * @param StoreExpenseRequest $request
     * @return JsonResponse
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $date = Carbon::parse($data['date']);
        $path = $request->file('attachment')?->store('expense-receipts', 'local');

        try {
            $expense = Expense::create([
                'expense_number' => 'EXP-'.$date->format('Ymd').'-'.Str::upper(Str::random(10)),
                'date' => $date,
                'chart_of_account_id' => $data['expense_account_id'],
                'cash_bank_account_id' => $data['cash_bank_account_id'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'attachment_path' => $path,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'submitted_by' => $request->user()->id,
            ]);
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        AuditService::log('create_expense_draft', 'expenses', 'Expense', $expense->id, null, $expense->except('attachment_path'));

        return ApiResponse::success(
            (new ExpenseResource($expense->load(['chartOfAccount', 'cashBankAccount', 'submitter', 'approver'])))->resolve(),
            'Expense draft created.',
            201,
        );
    }

    /**
     * Ajukan Pengeluaran ke Owner
     *
     * Mengajukan catatan pengeluaran berstatus 'draft' ke antrean persetujuan Owner (status berubah menjadi 'pending').
     *
     * @param Request $request
     * @param Expense $expense
     * @return JsonResponse
     */
    public function submit(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->status !== 'draft') {
            return ApiResponse::error('Only draft expenses can be submitted.', 'EXPENSE_ALREADY_PROCESSED', 409);
        }

        $expense = DB::transaction(function () use ($expense) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($expense->status !== 'draft') {
                throw new \DomainException('EXPENSE_ALREADY_PROCESSED');
            }
            $expense->update(['status' => 'pending']);
            Notification::create([
                'role' => 'owner',
                'type' => 'warning',
                'title' => 'New Expense Approval',
                'message' => "Expense {$expense->expense_number} is awaiting approval.",
                'link' => '/approvals',
            ]);
            AuditService::log('submit_expense', 'expenses', 'Expense', $expense->id, ['status' => 'draft'], ['status' => 'pending']);

            return $expense;
        });

        return ApiResponse::success(
            (new ExpenseResource($expense->load(['chartOfAccount', 'cashBankAccount', 'submitter', 'approver'])))->resolve(),
            'Expense submitted for approval.',
        );
    }

    /**
     * Setujui Pengeluaran Kas (Owner Only)
     *
     * Owner menyetujui pengajuan pengeluaran kas:
     * - Memvalidasi periode akuntansi pada tanggal pengeluaran masih berstatus open.
     * - Memotong saldo akun kas/bank asal dana.
     * - Membuat jurnal akuntansi berimbang otomatis (Debit Beban, Kredit Kas/Bank).
     * - Mengubah status pengeluaran menjadi 'approved'.
     *
     * @param Expense $expense
     * @param AccountingService $accounting
     * @return JsonResponse
     */
    public function approve(Expense $expense, AccountingService $accounting): JsonResponse
    {
        if ($expense->status !== 'pending') {
            return ApiResponse::error('Expense is not pending approval.', 'EXPENSE_ALREADY_PROCESSED', 409);
        }

        try {
            $expense = DB::transaction(function () use ($expense, $accounting) {
                $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
                if ($expense->status !== 'pending') {
                    throw new \DomainException('EXPENSE_ALREADY_PROCESSED');
                }
                $date = Carbon::parse($expense->date);
                $accounting->assertPeriodOpen($date);
                $cash = CashBankAccount::whereKey($expense->cash_bank_account_id)->lockForUpdate()->firstOrFail();

                $expense->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
                $cash->decrement('current_balance', $expense->amount);
                $accounting->createBalancedJournal('expense', $expense->id, $date, "Expense #{$expense->expense_number}: {$expense->description}", [
                    ['chart_of_account_id' => $expense->chart_of_account_id, 'debit' => $expense->amount, 'credit' => 0, 'memo' => $expense->description],
                    ['chart_of_account_id' => $cash->chart_of_account_id, 'debit' => 0, 'credit' => $expense->amount, 'memo' => "Payment from {$cash->name}"],
                ]);
                Notification::create([
                    'user_id' => $expense->submitted_by,
                    'type' => 'success',
                    'title' => 'Expense Approved',
                    'message' => "Expense {$expense->expense_number} has been approved.",
                    'link' => '/expenses',
                ]);
                AuditService::log('approve_expense', 'expenses', 'Expense', $expense->id, ['status' => 'pending'], ['status' => 'approved']);

                return $expense;
            });
        } catch (\DomainException $e) {
            return ApiResponse::error('Expense is not pending approval.', $e->getMessage(), 409);
        } catch (Throwable $e) {
            $code = str_contains(mb_strtolower($e->getMessage()), 'periode akuntansi') ? 'ACCOUNTING_PERIOD_CLOSED' : 'EXPENSE_APPROVAL_FAILED';

            return ApiResponse::error($e->getMessage(), $code, 422);
        }

        return ApiResponse::success(
            (new ExpenseResource($expense->load(['chartOfAccount', 'cashBankAccount', 'submitter', 'approver'])))->resolve(),
            'Expense approved and posted.',
        );
    }

    /**
     * Tolak Pengeluaran Kas (Owner Only)
     *
     * Owner menolak pengajuan pengeluaran kas dengan melampirkan alasan penolakan. Saldo kas tidak dipotong dan tidak ada jurnal akuntansi yang dicatat.
     *
     * @param RejectExpenseRequest $request
     * @param Expense $expense
     * @return JsonResponse
     */
    public function reject(RejectExpenseRequest $request, Expense $expense): JsonResponse
    {
        if ($expense->status !== 'pending') {
            return ApiResponse::error('Expense is not pending approval.', 'EXPENSE_ALREADY_PROCESSED', 409);
        }

        $expense = DB::transaction(function () use ($request, $expense) {
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($expense->status !== 'pending') {
                throw new \DomainException('EXPENSE_ALREADY_PROCESSED');
            }
            $expense->update([
                'status' => 'rejected',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejection_reason' => $request->string('rejection_reason')->toString(),
            ]);
            Notification::create([
                'user_id' => $expense->submitted_by,
                'type' => 'danger',
                'title' => 'Expense Rejected',
                'message' => "Expense {$expense->expense_number} has been rejected.",
                'link' => '/expenses',
            ]);
            AuditService::log('reject_expense', 'expenses', 'Expense', $expense->id, ['status' => 'pending'], ['status' => 'rejected']);

            return $expense;
        });

        return ApiResponse::success(
            (new ExpenseResource($expense->load(['chartOfAccount', 'cashBankAccount', 'submitter', 'approver'])))->resolve(),
            'Expense rejected.',
        );
    }

    /**
     * Unduh Lampiran Bukti Pengeluaran
     *
     * Mengunduh berkas gambar atau dokumen lampiran bukti transaksi pengeluaran (nota/kuitansi/struk).
     *
     * @param Expense $expense
     * @return StreamedResponse|JsonResponse
     */
    public function attachment(Expense $expense): StreamedResponse|JsonResponse
    {
        if (! $expense->attachment_path || ! Storage::disk('local')->exists($expense->attachment_path)) {
            return ApiResponse::error('Attachment not found.', 'RESOURCE_NOT_FOUND', 404);
        }

        return Storage::disk('local')->download($expense->attachment_path);
    }
}
