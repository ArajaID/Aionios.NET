<?php

namespace App\Http\Requests\Api\V1;

class StoreIncomeRequest extends IncomePreviewRequest
{
    public function rules(): array
    {
        return parent::rules() + ['preview_reference' => ['required', 'uuid']];
    }
}
