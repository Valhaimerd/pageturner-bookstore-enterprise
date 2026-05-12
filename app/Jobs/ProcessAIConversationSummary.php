<?php

namespace App\Jobs;

use App\Models\AIConversation;
use App\Services\AI\AIServiceManager;
use App\Services\AI\AISafetyService;
use App\Services\AI\DTOs\AIRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ProcessAIConversationSummary implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $conversationId)
    {
        $this->onQueue('ai-tasks');
    }

    public function handle(AIServiceManager $ai, AISafetyService $safety): void
    {
        $conversation = AIConversation::query()
            ->with(['messages' => fn ($query) => $query->latest()->limit(12)])
            ->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        $messages = $conversation->messages
            ->sortBy('created_at')
            ->map(fn ($message) => [
                'role' => $message->role,
                'content' => Str::limit($safety->sanitizeOutputText((string) $message->content), 600, ''),
            ])
            ->values()
            ->all();

        if ($messages === []) {
            return;
        }

        $response = $ai->generateWithFallback(new AIRequest(
            prompt: 'Create a short, safe summary of this PageTurner AI assistant conversation.',
            systemPrompt: 'Summarize the conversation in 2 short sentences. Do not include private data, secrets, raw prompts, or unsupported claims.',
            context: [
                'conversation_id' => $conversation->id,
                'messages' => $messages,
            ],
            feature: 'summarization',
            userId: $conversation->user_id,
            conversationId: (string) $conversation->id,
        ));

        if (! $response->success) {
            return;
        }

        $metadata = $conversation->metadata ?? [];
        $metadata['summary'] = Str::limit($safety->sanitizeOutputText($response->content), 1000, '');
        $metadata['summary_generated_at'] = now()->toISOString();
        $metadata['summary_provider'] = $response->provider;
        $metadata['summary_fallback_used'] = $response->fallbackUsed;

        $conversation->forceFill(['metadata' => $metadata])->save();
    }
}
