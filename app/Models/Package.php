<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'download_speed_mbps',
        'upload_speed_mbps',
        'price',
        'ppp_profile',
        'is_active',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'download_speed_mbps' => 'integer',
        'upload_speed_mbps' => 'integer',
        'is_active' => 'boolean',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
