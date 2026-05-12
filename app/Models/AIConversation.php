<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AIConversation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ai_conversations';

    protected $fillable = [
        'user_id',
        'session_id',
        'title',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(AIMessage::class, 'ai_conversation_id');
    }

    public function usageLogs()
    {
        return $this->hasMany(AIUsageLog::class, 'ai_conversation_id');
    }
}
