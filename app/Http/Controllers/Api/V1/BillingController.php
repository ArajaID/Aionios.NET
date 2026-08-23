<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
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
        return ApiResponse::success((new InvoiceResource($invoice->load('customer')))->resolve());
    }
}
