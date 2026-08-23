<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'income_number' => $this->income_number,
            'date' => $this->date?->toDateString(),
            'amount' => (string) $this->amount,
            'description' => $this->description,
            'reference' => $this->reference,
            'account' => $this->whenLoaded('chartOfAccount', fn () => [
                'id' => $this->chartOfAccount->id,
                'code' => $this->chartOfAccount->code,
                'name' => $this->chartOfAccount->name,
            ]),
            'cash_bank_account' => $this->whenLoaded('cashBankAccount', fn () => [
                'id' => $this->cashBankAccount->id,
                'name' => $this->cashBankAccount->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
