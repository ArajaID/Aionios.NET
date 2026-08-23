<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactivateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activation_date' => ['required', 'date'],
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where(fn (Builder $query) => $query->where('is_active', true))],
            'ont_id' => ['nullable', 'integer', 'exists:onts,id'],
            'pppoe_password' => ['nullable', 'string', 'min:8', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
