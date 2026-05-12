<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_messages';

    protected $fillable = [
        'ai_conversation_id',
        'user_id',
        'role',
        'content',
        'provider',
        'model',
        'confidence',
        'metadata',
    ];

    protected $casts = [
        'confidence' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(AIConversation::class, 'ai_conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
