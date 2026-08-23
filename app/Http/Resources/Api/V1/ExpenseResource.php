<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_number' => $this->expense_number,
            'date' => $this->date?->toDateString(),
            'amount' => (string) $this->amount,
            'description' => $this->description,
            'notes' => $this->notes,
            'status' => $this->status === 'pending' ? 'pending_approval' : $this->status,
            'rejection_reason' => $this->rejection_reason,
            'has_attachment' => $this->attachment_path !== null,
            'account' => $this->whenLoaded('chartOfAccount', fn () => [
                'id' => $this->chartOfAccount->id,
                'code' => $this->chartOfAccount->code,
                'name' => $this->chartOfAccount->name,
            ]),
            'cash_bank_account' => $this->whenLoaded('cashBankAccount', fn () => [
                'id' => $this->cashBankAccount->id,
                'name' => $this->cashBankAccount->name,
            ]),
            'submitted_by' => $this->whenLoaded('submitter', fn () => $this->submitter ? [
                'id' => $this->submitter->id,
                'name' => $this->submitter->name,
            ] : null),
            'approved_by' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'approved_at' => $this->approved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
