<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIAuditEvent extends Model
{
    use HasFactory;

    protected $table = 'ai_audit_events';

    protected $fillable = [
        'user_id',
        'feature',
        'action',
        'input_hash',
        'output_hash',
        'provider',
        'confidence',
        'risk_level',
        'metadata',
    ];

    protected $casts = [
        'confidence' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
