<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'provider',
        'fallback_provider',
        'model',
        'prompt',
        'normalized_intent',
        'candidate_book_ids',
        'recommended_book_ids',
        'answer',
        'status',
        'fallback_used',
        'fallback_reason',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'estimated_cost_cents',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'normalized_intent' => 'array',
        'candidate_book_ids' => 'array',
        'recommended_book_ids' => 'array',
        'fallback_used' => 'boolean',
        'estimated_cost_cents' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
