<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\Providers\FakeAIProvider;
use App\Services\AI\Providers\OllamaProvider;
use Throwable;

class AIServiceManager
{
    public function __construct(
        protected OllamaProvider $ollamaProvider,
        protected FakeAIProvider $fakeProvider,
        protected ?AIUsageTracker $usageTracker = null,
        protected ?AIAuditLogger $auditLogger = null,
        protected ?AISafetyService $safetyService = null,
    ) {}

    public function generate(AIRequest $request): AIResponse
    {
        $request = $this->normalize($request);

        if ($blocked = $this->blockedResponse($request)) {
            $this->recordBlockedInput($request, $blocked);

            return $blocked;
        }

        $response = $this->primaryProvider()->generate($request);
        $this->record($request, $response, $this->successAction($request));

        return $response;
    }

    public function generateForFeature(string $feature, string $prompt, array $context = []): AIResponse
    {
        return $this->generateWithFallback(new AIRequest(
            prompt: $prompt,
            context: $context,
            feature: $feature,
        ));
    }

    public function generateWithFallback(AIRequest $request): AIResponse
    {
        $request = $this->normalize($request);

        if ($blocked = $this->blockedResponse($request)) {
            $this->recordBlockedInput($request, $blocked);

            return $blocked;
        }

        if (! (bool) config('ai.enabled', true)) {
            $response = $this->fallback($request, 'Ollama is disabled.');
            $this->record($request, $response, $response->success ? AIAuditLogger::FALLBACK_TRIGGERED : AIAuditLogger::AI_UNAVAILABLE);

            return $response;
        }

        try {
            $response = $this->primaryProvider()->generate($request);

            if ($response->success) {
                $this->record($request, $response, $this->successAction($request));

                return $response;
            }

            $fallback = $this->fallback($request, $response->errorMessage ?: 'Ollama failed.');
            $this->record($request, $fallback, $fallback->success ? AIAuditLogger::FALLBACK_TRIGGERED : AIAuditLogger::AI_UNAVAILABLE);

            return $fallback;
        } catch (Throwable) {
            $fallback = $this->fallback($request, 'Ollama failed.');
            $this->record($request, $fallback, $fallback->success ? AIAuditLogger::FALLBACK_TRIGGERED : AIAuditLogger::AI_UNAVAILABLE);

            return $fallback;
        }
    }

    protected function fallback(AIRequest $request, string $reason): AIResponse
    {
        try {
            $response = $this->fakeProvider->generate($request);

            if ($response->success) {
                return new AIResponse(
                    success: true,
                    provider: $response->provider,
                    model: $response->model,
                    content: $response->content,
                    confidence: $response->confidence,
                    tokensInput: $response->tokensInput,
                    tokensOutput: $response->tokensOutput,
                    latencyMs: $response->latencyMs,
                    fallbackUsed: true,
                    errorMessage: $reason,
                    rawMetadata: array_merge($response->rawMetadata, ['fallback_reason' => $reason]),
                );
            }
        } catch (Throwable) {
            //
        }

        return new AIResponse(
            success: false,
            provider: 'none',
            model: null,
            content: 'AI service is temporarily unavailable. Please try again later.',
            fallbackUsed: true,
            errorMessage: 'AI service is temporarily unavailable.',
            rawMetadata: ['fallback_reason' => $reason],
        );
    }

    protected function primaryProvider(): AIProviderInterface
    {
        return match ((string) config('ai.provider', 'ollama')) {
            'fake' => $this->fakeProvider,
            default => $this->ollamaProvider,
        };
    }

    protected function normalize(AIRequest $request): AIRequest
    {
        return $request->withPrompt(trim($request->prompt));
    }

    protected function blockedResponse(AIRequest $request): ?AIResponse
    {
        $result = $this->safety()->validateInput($request);

        if (! ($result['allowed'] ?? false)) {
            return new AIResponse(
                success: false,
                provider: 'none',
                model: null,
                content: 'This AI request cannot be processed safely.',
                fallbackUsed: false,
                errorMessage: 'AI request blocked by safety policy.',
                rawMetadata: [
                    'error_code' => 'unsafe_input_blocked',
                    'safety_reason' => $result['reason'] ?? 'unsafe_input',
                    'risk_level' => $result['risk_level'] ?? 'high',
                ],
            );
        }

        return null;
    }

    protected function recordBlockedInput(AIRequest $request, AIResponse $response): void
    {
        $this->record($request, $response, AIAuditLogger::UNSAFE_INPUT_BLOCKED, [
            'risk_level' => $response->rawMetadata['risk_level'] ?? 'high',
            'safety_reason' => $response->rawMetadata['safety_reason'] ?? 'unsafe_input',
        ]);
    }

    protected function record(AIRequest $request, AIResponse $response, string $action, array $metadata = []): void
    {
        $metadata = array_merge($metadata, [
            'error_code' => $response->success ? null : ($response->rawMetadata['error_code'] ?? 'ai_unavailable'),
        ]);

        $this->usageTracker()->record($request, $response, $metadata);
        $this->auditLogger()->log($action, $request, $response, $metadata);
    }

    protected function successAction(AIRequest $request): string
    {
        return $request->feature === 'book_discovery_recommendations'
            ? AIAuditLogger::RECOMMENDATION_GENERATED
            : AIAuditLogger::CHAT_RESPONSE_GENERATED;
    }

    protected function usageTracker(): AIUsageTracker
    {
        return $this->usageTracker ??= app(AIUsageTracker::class);
    }

    protected function auditLogger(): AIAuditLogger
    {
        return $this->auditLogger ??= app(AIAuditLogger::class);
    }

    protected function safety(): AISafetyService
    {
        return $this->safetyService ??= app(AISafetyService::class);
    }
}
