<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'period',
        'issue_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'status',
        'is_prorata',
        'snapshot_data',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'is_prorata' => 'boolean',
        'snapshot_data' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function adjustmentRequests(): HasMany
    {
        return $this->hasMany(InvoiceAdjustmentRequest::class);
    }

    public function pendingAdjustmentRequest()
    {
        return $this->hasOne(InvoiceAdjustmentRequest::class)->where('status', 'pending');
    }
}
