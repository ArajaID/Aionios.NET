<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'name',
        'phone',
        'address',
        'installed_at',
        'activated_at',
        'package_id',
        'ont_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'installed_at' => 'date',
        'activated_at' => 'date',
    ];

    protected $appends = [
        'outstanding_amount',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function ont(): BelongsTo
    {
        return $this->belongsTo(Ont::class);
    }

    public function pppAccount(): HasOne
    {
        return $this->hasOne(PppAccount::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function unpaidInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->whereIn('status', ['unpaid', 'overdue']);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(CustomerPromotion::class);
    }

    public function activePromotion(): HasOne
    {
        return $this->hasOne(CustomerPromotion::class)
            ->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(CustomerStatusHistory::class)->latest();
    }

    public function packageChangeRequests(): HasMany
    {
        return $this->hasMany(PackageChangeRequest::class);
    }

    public function pendingPackageChangeRequest()
    {
        return $this->hasOne(PackageChangeRequest::class)->where('status', 'pending');
    }

    public function getOutstandingAmountAttribute(): float
    {
        return (float) $this->unpaidInvoices()->sum('total_amount');
    }
}
