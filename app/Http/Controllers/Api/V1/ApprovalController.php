<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\InvoiceAdjustmentRequest;
use App\Models\PackageChangeRequest;
use App\Models\ReversalRequest;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\MikrotikService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Persetujuan (Approvals)
 */
class ApprovalController extends Controller
{
    /**
     * Ringkasan Antrean Approval Owner
     *
     * Mengembalikan jumlah antrean persetujuan pending untuk beban kas, reversal pembayaran,
     * penyesuaian nominal invoice, dan pergantian paket pelanggan.
     */
    public function summary(): JsonResponse
    {
        return ApiResponse::success([
            'pending_expenses_count' => Expense::where('status', 'pending')->count(),
            'pending_reversals_count' => ReversalRequest::where('status', 'pending')->count(),
            'pending_invoice_adjustments_count' => InvoiceAdjustmentRequest::where('status', 'pending')->count(),
            'pending_package_changes_count' => PackageChangeRequest::where('status', 'pending')->count(),
            'total_pending' => Expense::where('status', 'pending')->count()
                + ReversalRequest::where('status', 'pending')->count()
                + InvoiceAdjustmentRequest::where('status', 'pending')->count()
                + PackageChangeRequest::where('status', 'pending')->count(),
        ]);
    }

    /**
     * Daftar Pengajuan Penyesuaian Tagihan
     *
     * @param Request $request status=pending|approved|rejected|all, per_page=integer
     */
    public function invoiceAdjustments(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');
        $query = InvoiceAdjustmentRequest::with(['invoice.customer', 'requester', 'approver'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $items = $query->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($items, $items->items());
    }

    /**
     * Setujui Penyesuaian Tagihan
     *
     * Menyetujui permohonan penyesuaian nominal tagihan invoice. Jika nominal baru menjadi Rp 0, status invoice otomatis diubah menjadi Lunas (PAID).
     */
    public function approveInvoiceAdjustment(InvoiceAdjustmentRequest $adjRequest): JsonResponse
    {
        if ($adjRequest->status !== 'pending') {
            return ApiResponse::error('Pengajuan penyesuaian tagihan ini sudah diproses sebelumnya.', 'INVALID_STATE', 400);
        }

        $user = auth()->user();
        $invoice = $adjRequest->invoice;

        $oldValues = $invoice->toArray();

        $invoice->update([
            'subtotal' => $adjRequest->new_subtotal,
            'discount_amount' => $adjRequest->new_discount_amount,
            'total_amount' => $adjRequest->new_total_amount,
            'status' => $adjRequest->new_total_amount <= 0 ? 'paid' : $invoice->status,
        ]);

        $adjRequest->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        AuditService::log(
            'approve_invoice_adjustment',
            'invoices',
            'Invoice',
            $invoice->id,
            $oldValues,
            $invoice->fresh()->toArray()
        );

        return ApiResponse::success([
            'invoice' => $invoice->fresh(),
            'request' => $adjRequest->fresh(),
        ], 'Penyesuaian tagihan berhasil disetujui.');
    }

    /**
     * Tolak Penyesuaian Tagihan
     *
     * Menolak pengajuan penyesuaian nominal tagihan dengan melampirkan alasan penolakan.
     */
    public function rejectInvoiceAdjustment(Request $httpRequest, InvoiceAdjustmentRequest $adjRequest): JsonResponse
    {
        if ($adjRequest->status !== 'pending') {
            return ApiResponse::error('Pengajuan penyesuaian tagihan ini sudah diproses sebelumnya.', 'INVALID_STATE', 400);
        }

        $validated = $httpRequest->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $adjRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        AuditService::log(
            'reject_invoice_adjustment',
            'invoices',
            'InvoiceAdjustmentRequest',
            $adjRequest->id,
            ['status' => 'pending'],
            ['status' => 'rejected', 'rejection_reason' => $validated['rejection_reason']]
        );

        return ApiResponse::success($adjRequest->fresh(), 'Pengajuan penyesuaian tagihan telah ditolak.');
    }

    /**
     * Daftar Pengajuan Ganti Paket Pelanggan
     *
     * @param Request $request status=pending|approved|rejected|all, per_page=integer
     */
    public function packageChanges(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');
        $query = PackageChangeRequest::with(['customer', 'oldPackage', 'newPackage', 'requester', 'approver'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $items = $query->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($items, $items->items());
    }

    /**
     * Setujui Ganti Paket Pelanggan
     *
     * Menyetujui permohonan pergantian paket pelanggan dan langsung melakukan sinkronisasi profil PPP ke router MikroTik secara real-time.
     */
    public function approvePackageChange(PackageChangeRequest $pkgRequest, MikrotikService $mikrotikService): JsonResponse
    {
        if ($pkgRequest->status !== 'pending') {
            return ApiResponse::error('Pengajuan ganti paket ini sudah diproses sebelumnya.', 'INVALID_STATE', 400);
        }

        $user = auth()->user();
        $customer = $pkgRequest->customer;
        $newPackage = $pkgRequest->newPackage;

        $oldValues = $customer->toArray();

        $customer->update([
            'package_id' => $newPackage->id,
        ]);

        // Sync MikroTik
        $ppp = $customer->pppAccount;
        if ($ppp && $customer->status === 'active') {
            $activePromo = $customer->activePromotion;
            $targetProfile = ($activePromo && $activePromo->promotion?->promo_ppp_profile)
                ? $activePromo->promotion->promo_ppp_profile
                : $newPackage->ppp_profile;

            if ($targetProfile) {
                $mikrotikService->updateProfile($ppp, $targetProfile);
            }
        }

        $pkgRequest->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        AuditService::log(
            'approve_package_change',
            'customers',
            'Customer',
            $customer->id,
            $oldValues,
            $customer->fresh()->toArray()
        );

        return ApiResponse::success([
            'customer' => $customer->fresh()->load('package'),
            'request' => $pkgRequest->fresh(),
        ], "Perubahan paket untuk {$customer->name} berhasil disetujui dan disinkronisasi ke MikroTik.");
    }

    /**
     * Tolak Ganti Paket Pelanggan
     *
     * Menolak pengajuan ganti paket pelanggan dengan melampirkan alasan penolakan.
     */
    public function rejectPackageChange(Request $httpRequest, PackageChangeRequest $pkgRequest): JsonResponse
    {
        if ($pkgRequest->status !== 'pending') {
            return ApiResponse::error('Pengajuan ganti paket ini sudah diproses sebelumnya.', 'INVALID_STATE', 400);
        }

        $validated = $httpRequest->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $pkgRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        AuditService::log(
            'reject_package_change',
            'customers',
            'PackageChangeRequest',
            $pkgRequest->id,
            ['status' => 'pending'],
            ['status' => 'rejected', 'rejection_reason' => $validated['rejection_reason']]
        );

        return ApiResponse::success($pkgRequest->fresh(), 'Pengajuan ganti paket telah ditolak.');
    }

    /**
     * Daftar Pengajuan Reversal Pembayaran
     *
     * @param Request $request status=pending|approved|rejected|all, per_page=integer
     */
    public function reversals(Request $request): JsonResponse
    {
        $status = $request->input('status', 'pending');
        $query = ReversalRequest::with(['payment.customer', 'requester', 'approver'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $items = $query->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($items, $items->items());
    }

    /**
     * Setujui Reversal Pembayaran
     *
     * Menyetujui reversal pembayaran dan memposting jurnal pembalik kas/bank.
     */
    public function approveReversal(
        ReversalRequest $revRequest,
        AccountingService $accountingService,
        PaymentService $paymentService
    ): JsonResponse {
        if ($revRequest->status !== 'pending') {
            return ApiResponse::error('Permintaan reversal ini sudah diproses sebelumnya.', 'INVALID_STATE', 400);
        }

        $user = auth()->user();
        $payment = $revRequest->payment;

        $paymentService->processReversal($payment, $user, $revRequest->reason);

        $revRequest->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        AuditService::log(
            'approve_reversal',
            'payments',
            'Payment',
            $payment->id,
            ['status' => 'paid'],
            ['status' => 'reversed']
        );

        return ApiResponse::success($revRequest->fresh(), 'Reversal pembayaran berhasil disetujui.');
    }

    /**
     * Tolak Reversal Pembayaran
     *
     * Menolak permohonan reversal pembayaran dengan melampirkan alasan penolakan.
     */
    public function rejectReversal(Request $httpRequest, ReversalRequest $revRequest): JsonResponse
    {
        if ($revRequest->status !== 'pending') {
            return ApiResponse::error('Permintaan reversal ini sudah diproses sebelumnya.', 'INVALID_STATE', 400);
        }

        $validated = $httpRequest->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $revRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        AuditService::log(
            'reject_reversal',
            'payments',
            'ReversalRequest',
            $revRequest->id,
            ['status' => 'pending'],
            ['status' => 'rejected', 'rejection_reason' => $validated['rejection_reason']]
        );

        return ApiResponse::success($revRequest->fresh(), 'Permintaan reversal telah ditolak.');
    }
}
