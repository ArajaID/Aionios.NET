<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MikrotikRouter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'timeout',
        'api_type',
        'is_active',
        'status',
        'last_connected_at',
        'resource_data',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'port' => 'integer',
        'timeout' => 'integer',
        'password' => 'encrypted',
        'is_active' => 'boolean',
        'last_connected_at' => 'datetime',
        'resource_data' => 'array',
    ];

    public function networkLogs(): HasMany
    {
        return $this->hasMany(NetworkLog::class, 'router_id');
    }
}
