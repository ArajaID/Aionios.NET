<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'installed_at' => $this->installed_at?->toDateString(),
            'activated_at' => $this->activated_at?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'package' => $this->whenLoaded('package', fn () => $this->package ? [
                'id' => $this->package->id,
                'code' => $this->package->code,
                'name' => $this->package->name,
                'download_speed_mbps' => $this->package->download_speed_mbps,
                'upload_speed_mbps' => $this->package->upload_speed_mbps,
                'price' => number_format((float) $this->package->price, 2, '.', ''),
            ] : null),
            'pppoe' => $this->whenLoaded('pppAccount', fn () => $this->pppAccount ? [
                'username' => $this->pppAccount->username,
                'profile' => $this->pppAccount->profile,
                'status' => $this->pppAccount->status,
                'current_ip' => $this->pppAccount->current_ip,
                'last_sync_at' => $this->pppAccount->last_sync_at?->toISOString(),
            ] : null),
            'ont' => $this->whenLoaded('ont', fn () => $this->ont ? [
                'id' => $this->ont->id,
                'ont_id' => $this->ont->ont_id,
                'brand' => $this->ont->brand,
                'model' => $this->ont->model,
                'serial_number' => $this->ont->serial_number,
                'status' => $this->ont->status,
                'condition' => $this->ont->condition,
            ] : null),
            'billing' => [
                'outstanding' => number_format((float) $this->outstanding_amount, 2, '.', ''),
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
