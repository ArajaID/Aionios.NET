<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CustomerLifecycleRequest;
use App\Http\Requests\Api\V1\PaymentPreviewRequest;
use App\Http\Requests\Api\V1\StorePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ReversalRequest;
use App\Services\AuditService;
use App\Services\PaymentService;
use App\Services\PreviewReferenceService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @tags Pembayaran (Payments)
 */
class PaymentController extends Controller
{
    /**
     * Daftar Transaksi Pembayaran
     *
     * Menampilkan riwayat transaksi pembayaran tagihan invoice pelanggan dengan pencarian nomor bayar/nama/ID pelanggan, filter metode bayar (manual/qris), filter status (posted/reversed), sorting, dan paginasi data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'in:manual,qris'],
            'status' => ['nullable', 'in:posted,reversed'],
            'sort' => ['nullable', 'in:created_at,-created_at,payment_date,-payment_date,gross_amount,-gross_amount'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = Payment::with(['customer', 'cashBankAccount', 'allocations.invoice']);
        if ($search = ($validated['search'] ?? null)) {
            $query->where(function ($builder) use ($search) {
                $builder->where('payment_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_id', 'like', "%{$search}%"));
            });
        }
        if (isset($validated['method'])) {
            $query->where('payment_method', $validated['method']);
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        $sort = $validated['sort'] ?? '-created_at';
        $paginator = $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->paginate($validated['per_page'] ?? 20);

        return ApiResponse::paginated($paginator, PaymentResource::collection($paginator->getCollection())->resolve());
    }

    /**
     * Detail Transaksi Pembayaran
     *
     * Menampilkan rincian transaksi pembayaran tertentu, mencakup akun kas/bank penerima dana, alokasi nominal ke nomor-nomor invoice tagihan, dan rincian potongan MDR jika bayar via QRIS.
     *
     * @param Payment $payment
     * @return JsonResponse
     */
    public function show(Payment $payment): JsonResponse
    {
        return ApiResponse::success((new PaymentResource(
            $payment->load(['customer', 'cashBankAccount', 'allocations.invoice'])
        ))->resolve());
    }

    /**
     * Pratinjau Alokasi Pembayaran Tagihan
     *
     * Melakukan simulasi perhitungan pembayaran sebelum transaksi dibukukan secara permanen:
     * - Memvalidasi ketersediaan invoice tertunggak dan menerapkan alokasi berurutan (FIFO).
     * - Menghitung estimasi potongan fee MDR jika metode pembayaran QRIS dipilih.
     * - Memastikan periode akuntansi pada tanggal transaksi berstatus terbuka (open).
     * - Menghasilkan token `preview_reference` (berlaku 10 menit) serta simulasi jurnal debit dan kredit.
     *
     * @param PaymentPreviewRequest $request
     * @param PaymentService $payments
     * @param PreviewReferenceService $references
     * @return JsonResponse
     */
    public function preview(
        PaymentPreviewRequest $request,
        PaymentService $payments,
        PreviewReferenceService $references
    ): JsonResponse {
        try {
            $customer = Customer::findOrFail($request->integer('customer_id'));
            $preview = $payments->previewPayment(
                $customer,
                $request->string('payment_method')->toString(),
                $request->integer('cash_bank_account_id'),
            );
        } catch (Throwable $e) {
            return ApiResponse::error($e->getMessage(), 'PAYMENT_PREVIEW_FAILED', 422);
        }

        $snapshot = [
            'customer_id' => $customer->id,
            'payment_method' => $request->string('payment_method')->toString(),
            'cash_bank_account_id' => $request->integer('cash_bank_account_id'),
            'invoice_ids' => $preview['invoices']->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'gross_amount' => number_format((float) $preview['gross_amount'], 2, '.', ''),
        ];
        $reference = $references->issue('payment', $request->user()->id, $snapshot);

        return ApiResponse::success([
            'preview_reference' => $reference,
            'expires_in_seconds' => 600,
            'customer' => [
                'id' => $customer->id,
                'customer_id' => $customer->customer_id,
                'name' => $customer->name,
            ],
            'invoices' => $preview['invoices']->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'period' => $invoice->period,
                'amount' => (string) $invoice->total_amount,
            ])->values(),
            'gross_amount' => $snapshot['gross_amount'],
            'mdr_percentage' => number_format((float) $preview['mdr_percentage'], 2, '.', ''),
            'mdr_amount' => number_format((float) $preview['mdr_fee'], 2, '.', ''),
            'net_settlement' => number_format((float) $preview['net_amount'], 2, '.', ''),
            'cash_bank_account' => [
                'id' => $preview['cash_bank_account']->id,
                'name' => $preview['cash_bank_account']->name,
            ],
            'journal_preview' => collect($preview['journal_preview'])->map(fn ($line) => [
                ...$line,
                'debit' => number_format((float) $line['debit'], 2, '.', ''),
                'credit' => number_format((float) $line['credit'], 2, '.', ''),
            ])->all(),
        ]);
    }

    /**
     * Simpan Transaksi Pembayaran Tagihan
     *
     * Membukukan pembayaran tagihan secara definitif menggunakan token `preview_reference` yang valid:
     * - Mengubah status invoice menjadi 'paid' (lunas).
     * - Menambah saldo akun kas/bank penerima dana.
     * - Membuat jurnal akuntansi penerimaan kas otomatis.
     * - Menjadwalkan pembukaan blokir isolir pelanggan secara otomatis jika pelanggan berstatus 'isolated'.
     *
     * @param StorePaymentRequest $request
     * @param PaymentService $payments
     * @param PreviewReferenceService $references
     * @return JsonResponse
     */
    public function store(
        StorePaymentRequest $request,
        PaymentService $payments,
        PreviewReferenceService $references
    ): JsonResponse {
        $data = $request->validated();
        $reference = $references->get('payment', $data['preview_reference'], $request->user()->id);
        if (! $reference) {
            return ApiResponse::error('Payment preview is invalid or expired.', 'PAYMENT_PREVIEW_EXPIRED', 409);
        }
        if (
            $reference['customer_id'] !== (int) $data['customer_id']
            || $reference['payment_method'] !== $data['payment_method']
            || $reference['cash_bank_account_id'] !== (int) $data['cash_bank_account_id']
        ) {
            return ApiResponse::error('Payment payload does not match the preview.', 'PAYMENT_PREVIEW_MISMATCH', 409);
        }

        try {
            $customer = Customer::findOrFail($data['customer_id']);
            $current = $payments->previewPayment($customer, $data['payment_method'], $data['cash_bank_account_id']);
            $currentIds = $current['invoices']->pluck('id')->map(fn ($id) => (int) $id)->all();
            $currentGross = number_format((float) $current['gross_amount'], 2, '.', '');
            if ($currentIds !== $reference['invoice_ids'] || $currentGross !== $reference['gross_amount']) {
                return ApiResponse::error('Outstanding invoices changed after preview.', 'PAYMENT_STATE_CHANGED', 409);
            }

            $payment = $payments->processPayment(
                $customer,
                $data['payment_method'],
                $data['cash_bank_account_id'],
                Carbon::parse($data['payment_date'] ?? now()),
                null,
                $data['notes'] ?? null,
            );
        } catch (Throwable $e) {
            $code = str_contains(mb_strtolower($e->getMessage()), 'periode akuntansi')
                ? 'ACCOUNTING_PERIOD_CLOSED'
                : (str_contains(mb_strtolower($e->getMessage()), 'outstanding') ? 'PAYMENT_ALREADY_POSTED' : 'PAYMENT_POST_FAILED');

            return ApiResponse::error($e->getMessage(), $code, $code === 'PAYMENT_ALREADY_POSTED' ? 409 : 422);
        }

        $references->forget('payment', $data['preview_reference']);

        return ApiResponse::success(
            (new PaymentResource($payment->load(['customer', 'cashBankAccount', 'allocations.invoice'])))->resolve(),
            'Payment posted successfully.',
            201,
        );
    }

    /**
     * Ajukan Reversal (Pembatalan) Pembayaran
     *
     * Petugas mengajukan permohonan pembatalan transaksi pembayaran salah catat atau duplikat (ReversalRequest) ke Owner untuk diverifikasi dan disetujui.
     *
     * @param CustomerLifecycleRequest $request
     * @param Payment $payment
     * @return JsonResponse
     */
    public function requestReversal(CustomerLifecycleRequest $request, Payment $payment): JsonResponse
    {
        if ($payment->status !== 'posted') {
            return ApiResponse::error('Payment is not eligible for reversal.', 'PAYMENT_ALREADY_REVERSED', 409);
        }
        if ($payment->reversalRequest()->where('status', 'pending')->exists()) {
            return ApiResponse::error('A reversal request is already pending.', 'APPROVAL_ALREADY_PROCESSED', 409);
        }

        $reversal = ReversalRequest::create([
            'transaction_type' => 'payment',
            'transaction_id' => $payment->id,
            'requested_by' => $request->user()->id,
            'reason' => $request->string('reason')->toString(),
            'status' => 'pending',
        ]);
        AuditService::log('request_payment_reversal', 'payments', 'Payment', $payment->id, null, ['reversal_request_id' => $reversal->id]);

        return ApiResponse::success([
            'id' => $reversal->id,
            'resource_type' => 'payment',
            'resource_id' => $payment->id,
            'status' => $reversal->status,
            'reason' => $reversal->reason,
        ], 'Reversal request submitted.', 201);
    }
}
