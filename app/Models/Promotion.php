<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'discount_type',
        'discount_value',
        'duration_months',
        'promo_ppp_profile',
        'is_active',
        'description',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'duration_months' => 'integer',
        'is_active' => 'boolean',
    ];

    public function customerPromotions(): HasMany
    {
        return $this->hasMany(CustomerPromotion::class);
    }
}
