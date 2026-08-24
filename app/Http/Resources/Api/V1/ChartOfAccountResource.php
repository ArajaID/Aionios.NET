<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'category' => $this->category,
            'normal_balance' => $this->normal_balance,
            'is_active' => (bool) $this->is_active,
            'is_system' => (bool) $this->is_system,
            'cash_bank_accounts' => $this->whenLoaded('cashBankAccounts', fn () => $this->cashBankAccounts->map(fn ($cb) => [
                'id' => $cb->id,
                'name' => $cb->name,
                'bank_name' => $cb->bank_name,
                'account_number' => $cb->account_number,
                'current_balance' => number_format((float) $cb->current_balance, 2, '.', ''),
                'is_active' => (bool) $cb->is_active,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
