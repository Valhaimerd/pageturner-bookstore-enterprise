<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TwoFactorSecret extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'method',
        'secret',
        'recovery_codes',
        'confirmed_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'recovery_codes' => 'array',
            'confirmed_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
