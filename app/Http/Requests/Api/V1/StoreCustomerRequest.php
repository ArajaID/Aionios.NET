<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'string', 'max:50', 'unique:customers,customer_id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:2000'],
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where(fn (Builder $query) => $query->where('is_active', true))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
