<?php

namespace App\Services\AI;

use App\Models\AIConversation;
use App\Models\AIUsageLog;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;

class AIUsageTracker
{
    public function record(AIRequest $request, AIResponse $response, array $metadata = []): AIUsageLog
    {
        return AIUsageLog::create([
            'user_id' => $request->userId,
            'ai_conversation_id' => $this->conversationId($request->conversationId),
            'provider' => $response->provider,
            'model' => $response->model,
            'feature' => $request->feature,
            'tokens_input' => $response->tokensInput ?? 0,
            'tokens_output' => $response->tokensOutput ?? 0,
            'latency_ms' => $response->latencyMs,
            'fallback_used' => $response->fallbackUsed,
            'success' => $response->success,
            'error_code' => $this->errorCode($response),
            'cost_estimate' => 0,
            'metadata' => $this->sanitizeMetadata(array_merge($response->rawMetadata, $metadata)),
        ]);
    }

    public function sanitizeMetadata(array $metadata): array
    {
        $blocked = ['api_key', 'authorization', 'password', 'prompt', 'raw_prompt', 'content', 'stack', 'trace', 'exception'];
        $clean = [];

        foreach ($metadata as $key => $value) {
            $normalized = strtolower((string) $key);

            if (in_array($normalized, $blocked, true) || str_contains($normalized, 'token') || str_contains($normalized, 'secret')) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitizeMetadata($value) : $value;
        }

        return $clean;
    }

    protected function conversationId(?string $conversationId): ?int
    {
        if ($conversationId === null || ! ctype_digit($conversationId)) {
            return null;
        }

        $id = (int) $conversationId;

        return AIConversation::whereKey($id)->exists() ? $id : null;
    }

    protected function errorCode(AIResponse $response): ?string
    {
        if (is_string($response->rawMetadata['error_code'] ?? null)) {
            return $response->rawMetadata['error_code'];
        }

        if ($response->success && ! $response->fallbackUsed) {
            return null;
        }

        if (! $response->success) {
            return 'ai_unavailable';
        }

        return 'fallback_used';
    }
}
