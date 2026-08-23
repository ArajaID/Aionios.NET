<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnOntRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition' => ['required', Rule::in(['good', 'fair', 'bad'])],
            'status' => ['required', Rule::in(['available', 'returned', 'damaged', 'lost'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
