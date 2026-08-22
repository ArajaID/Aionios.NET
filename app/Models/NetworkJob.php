<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NetworkJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'command',
        'target_type',
        'target_id',
        'payload',
        'status',
        'error_message',
        'attempts',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
    ];
}
