<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'revenue_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->where('type', 'revenue')->where('is_active', true))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'cash_bank_account_id' => ['required', 'integer', Rule::exists('cash_bank_accounts', 'id')->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
