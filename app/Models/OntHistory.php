<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OntHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'ont_id',
        'customer_id',
        'action',
        'condition',
        'admin_id',
        'notes',
    ];

    public function ont(): BelongsTo
    {
        return $this->belongsTo(Ont::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
