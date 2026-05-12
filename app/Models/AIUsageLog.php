<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIUsageLog extends Model
{
    use HasFactory;

    protected $table = 'ai_usage_logs';

    protected $fillable = [
        'user_id',
        'ai_conversation_id',
        'provider',
        'model',
        'feature',
        'tokens_input',
        'tokens_output',
        'latency_ms',
        'fallback_used',
        'success',
        'error_code',
        'cost_estimate',
        'metadata',
    ];

    protected $casts = [
        'fallback_used' => 'boolean',
        'success' => 'boolean',
        'cost_estimate' => 'decimal:6',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(AIConversation::class, 'ai_conversation_id');
    }
}
