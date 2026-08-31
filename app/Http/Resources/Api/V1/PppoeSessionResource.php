<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PppoeSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->resource['customer_id'],
            'customer_code' => $this->resource['customer_code'],
            'customer_name' => $this->resource['customer_name'],
            'username' => $this->resource['username'],
            'profile' => $this->resource['profile'],
            'is_isolated' => $this->resource['is_isolated'],
            'is_online' => $this->resource['is_online'],
            'session' => $this->resource['session'],
        ];
    }
}
