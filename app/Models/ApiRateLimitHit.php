<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRateLimitHit extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'tier',
        'endpoint',
        'method',
        'ip_address',
        'limit',
        'remaining',
        'retry_after',
        'status_code',
        'throttled',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'throttled' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
