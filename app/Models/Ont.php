<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ont extends Model
{
    use HasFactory;

    protected $fillable = [
        'ont_id',
        'brand',
        'model',
        'serial_number',
        'mac_address',
        'status',
        'condition',
        'current_customer_id',
        'installed_at',
        'notes',
    ];

    protected $casts = [
        'installed_at' => 'date',
    ];

    public function currentCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'current_customer_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OntHistory::class)->latest();
    }
}
