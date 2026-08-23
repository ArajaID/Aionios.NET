<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'customer_id' => $this->customer->customer_id,
                'name' => $this->customer->name,
            ]),
            'payment_date' => $this->payment_date?->toDateString(),
            'payment_method' => $this->payment_method,
            'gross_amount' => (string) $this->gross_amount,
            'mdr_percentage' => (string) $this->mdr_percentage,
            'mdr_amount' => (string) $this->mdr_fee,
            'net_settlement' => (string) $this->net_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'cash_bank_account' => $this->whenLoaded('cashBankAccount', fn () => [
                'id' => $this->cashBankAccount->id,
                'name' => $this->cashBankAccount->name,
                'bank_name' => $this->cashBankAccount->bank_name,
            ]),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'invoice_id' => $allocation->invoice_id,
                'invoice_number' => $allocation->relationLoaded('invoice') ? $allocation->invoice?->invoice_number : null,
                'amount' => number_format((float) $allocation->amount, 2, '.', ''),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
