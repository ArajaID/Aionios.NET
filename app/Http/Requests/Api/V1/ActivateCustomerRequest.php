<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivateCustomerRequest extends FormRequest
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
            'ppp_profile_id' => ['nullable', 'integer'],
            'pppoe_username' => ['required', 'string', 'max:100', 'unique:ppp_accounts,username'],
            'pppoe_password' => ['required', 'string', 'min:8', 'max:255'],
            'ont_id' => ['nullable', 'integer', 'exists:onts,id'],
        ];
    }
}
