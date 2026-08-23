<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OntResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ont_id' => $this->ont_id,
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'mac_address' => $this->mac_address,
            'status' => $this->status,
            'condition' => $this->condition,
            'installed_at' => $this->installed_at?->toDateString(),
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('currentCustomer', fn () => $this->currentCustomer ? [
                'id' => $this->currentCustomer->id,
                'customer_id' => $this->currentCustomer->customer_id,
                'name' => $this->currentCustomer->name,
            ] : null),
        ];
    }
}
