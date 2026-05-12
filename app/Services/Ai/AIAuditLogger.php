<?php

namespace App\Services\AI;

use App\Models\AIAuditEvent;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use Illuminate\Support\Facades\Log;

class AIAuditLogger
{
    public const CHAT_RESPONSE_GENERATED = 'chat_response_generated';

    public const RECOMMENDATION_GENERATED = 'recommendation_generated';

    public const FALLBACK_TRIGGERED = 'fallback_triggered';

    public const UNSAFE_INPUT_BLOCKED = 'unsafe_input_blocked';

    public const AI_UNAVAILABLE = 'ai_unavailable';

    public function log(string $action, AIRequest $request, AIResponse $response, array $metadata = []): AIAuditEvent
    {
        $riskLevel = $metadata['risk_level'] ?? $this->riskLevel($action);
        unset($metadata['risk_level']);

        $record = [
            'user_id' => $request->userId,
            'feature' => $request->feature,
            'action' => $action,
            'input_hash' => $this->hash($request->prompt),
            'output_hash' => $response->content !== '' ? $this->hash($response->content) : null,
            'provider' => $response->provider !== 'none' ? $response->provider : null,
            'confidence' => $response->confidence,
            'risk_level' => $riskLevel,
            'metadata' => $this->sanitizeMetadata(array_merge([
                'fallback_used' => $response->fallbackUsed,
                'success' => $response->success,
                'error_code' => $response->success ? null : 'ai_unavailable',
            ], $metadata)),
        ];

        $event = AIAuditEvent::create($record);

        Log::channel('ai_audit')->info('AI audit event', [
            'action' => $record['action'],
            'feature' => $record['feature'],
            'provider' => $record['provider'],
            'user_id' => $record['user_id'],
            'risk_level' => $record['risk_level'],
            'input_hash' => $record['input_hash'],
            'output_hash' => $record['output_hash'],
            'metadata' => $record['metadata'],
        ]);

        return $event;
    }

    public function sanitizeMetadata(array $metadata): array
    {
        $blocked = ['api_key', 'authorization', 'password', 'prompt', 'raw_prompt', 'content', 'stack', 'trace', 'exception'];
        $clean = [];

        foreach ($metadata as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized = strtolower((string) $key);

            if (in_array($normalized, $blocked, true) || str_contains($normalized, 'token') || str_contains($normalized, 'secret')) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitizeMetadata($value) : $value;
        }

        return $clean;
    }

    protected function riskLevel(string $action): string
    {
        return match ($action) {
            self::UNSAFE_INPUT_BLOCKED => 'high',
            self::FALLBACK_TRIGGERED, self::AI_UNAVAILABLE => 'medium',
            default => 'low',
        };
    }

    protected function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
