<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssignOntRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ont_id' => ['required', 'integer', 'exists:onts,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
