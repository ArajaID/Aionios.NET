<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceAdjustmentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'requested_by',
        'old_subtotal',
        'new_subtotal',
        'old_discount_amount',
        'new_discount_amount',
        'old_total_amount',
        'new_total_amount',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'old_subtotal' => 'decimal:2',
        'new_subtotal' => 'decimal:2',
        'old_discount_amount' => 'decimal:2',
        'new_discount_amount' => 'decimal:2',
        'old_total_amount' => 'decimal:2',
        'new_total_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
