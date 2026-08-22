<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'customer_id',
        'payment_date',
        'payment_method',
        'cash_bank_account_id',
        'gross_amount',
        'mdr_percentage',
        'mdr_fee',
        'net_amount',
        'notes',
        'status',
        'received_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'gross_amount' => 'decimal:2',
        'mdr_percentage' => 'decimal:2',
        'mdr_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashBankAccount(): BelongsTo
    {
        return $this->belongsTo(CashBankAccount::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function reversalRequest(): HasOne
    {
        return $this->hasOne(ReversalRequest::class, 'transaction_id')->where('transaction_type', 'payment');
    }
}
