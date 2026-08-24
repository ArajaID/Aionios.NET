<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceAdjustmentRequest;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function customerInvoices(Customer $customer, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:unpaid,paid,overdue,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $customer->invoices()->with('customer');
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        $paginator = $query->latest('issue_date')->paginate($validated['per_page'] ?? 20);

        return ApiResponse::paginated($paginator, InvoiceResource::collection($paginator->getCollection())->resolve());
    }

    public function outstanding(Customer $customer): JsonResponse
    {
        $invoices = $customer->unpaidInvoices()->with('customer')->orderBy('period')->get();

        return ApiResponse::success([
            'customer' => [
                'id' => $customer->id,
                'customer_id' => $customer->customer_id,
                'name' => $customer->name,
            ],
            'invoices' => InvoiceResource::collection($invoices)->resolve(),
            'total' => number_format((float) $invoices->sum('total_amount'), 2, '.', ''),
        ]);
    }

    public function invoice(Invoice $invoice): JsonResponse
    {
        return ApiResponse::success(
            (new InvoiceResource($invoice->load(['customer', 'pendingAdjustmentRequest.requester'])))->resolve()
        );
    }

    public function adjust(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $subtotal = (float) $validated['subtotal'];
        $discount = (float) ($validated['discount_amount'] ?? 0);
        $newTotal = max(0, $subtotal - $discount);

        if ($user && $user->isOwner()) {
            $oldValues = $invoice->toArray();

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $newTotal,
                'status' => $newTotal <= 0 ? 'paid' : $invoice->status,
                'notes' => $validated['notes'] ?? $invoice->notes,
            ]);

            AuditService::log(
                'adjust_invoice_nominal',
                'invoices',
                'Invoice',
                $invoice->id,
                $oldValues,
                $invoice->fresh()->toArray()
            );

            return ApiResponse::success([
                'invoice' => (new InvoiceResource($invoice->fresh()->load('customer')))->resolve(),
                'status' => 'applied_directly',
            ], 'Nominal tagihan berhasil diperbarui langsung oleh Owner.');
        }

        // Staff: create approval request
        $adjRequest = InvoiceAdjustmentRequest::create([
            'invoice_id' => $invoice->id,
            'requested_by' => $user->id,
            'old_subtotal' => $invoice->subtotal,
            'new_subtotal' => $subtotal,
            'old_discount_amount' => $invoice->discount_amount,
            'new_discount_amount' => $discount,
            'old_total_amount' => $invoice->total_amount,
            'new_total_amount' => $newTotal,
            'reason' => $validated['notes'] ?? 'Pengajuan penyesuaian nominal tagihan via API',
            'status' => 'pending',
        ]);

        AuditService::log(
            'request_invoice_adjustment',
            'invoices',
            'InvoiceAdjustmentRequest',
            $adjRequest->id,
            null,
            $adjRequest->toArray()
        );

        return ApiResponse::success([
            'adjustment_request' => $adjRequest->fresh(),
            'status' => 'approval_pending',
        ], 'Pengajuan penyesuaian tagihan berhasil dikirim ke Owner untuk disetujui.', 202);
    }
}
