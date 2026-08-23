<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'payment_method' => ['required', Rule::in(['manual', 'qris'])],
            'cash_bank_account_id' => ['required', 'integer', Rule::exists('cash_bank_accounts', 'id')->where('is_active', true)],
        ];
    }
}
