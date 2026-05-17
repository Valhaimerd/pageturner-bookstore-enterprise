<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\Providers\FakeAIProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Throwable;

class AIServiceManager
{
    public function __construct(
        protected OllamaProvider $ollamaProvider,
        protected FakeAIProvider $fakeProvider,
        protected ?AIUsageTracker $usageTracker = null,
        protected ?AIAuditLogger $auditLogger = null,
        protected ?AISafetyService $safetyService = null,
        protected ?OpenAIProvider $openAIProvider = null,
        protected ?GeminiProvider $geminiProvider = null,
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
            $response = $this->generateFromChain($request, [$this->primaryName()], $this->providerLabel($this->primaryName()).' is disabled.');
            $this->record($request, $response, $response->success ? AIAuditLogger::FALLBACK_TRIGGERED : AIAuditLogger::AI_UNAVAILABLE);

            return $response;
        }

        $response = $this->generateFromChain($request);
        $this->record(
            $request,
            $response,
            $response->success
                ? ($response->fallbackUsed ? AIAuditLogger::FALLBACK_TRIGGERED : $this->successAction($request))
                : AIAuditLogger::AI_UNAVAILABLE
        );

        return $response;
    }

    protected function generateFromChain(AIRequest $request, array $skipProviders = [], ?string $initialReason = null): AIResponse
    {
        $lastReason = $initialReason;
        $attempt = 0;
        $skipProviders = array_map('strtolower', $skipProviders);

        foreach ($this->providerChain() as $providerName) {
            if (in_array($providerName, $skipProviders, true)) {
                continue;
            }

            $attempt++;
            $provider = $this->provider($providerName);

            if (! $provider) {
                $lastReason = $this->providerLabel($providerName).' is not configured.';

                continue;
            }

            try {
                $response = $provider->generate($request);
            } catch (Throwable) {
                $lastReason = $this->providerLabel($providerName).' failed.';

                continue;
            }

            if ($response->success) {
                return $attempt > 1 || $initialReason !== null
                    ? $this->markFallback($response, $lastReason ?: $this->providerLabel($this->primaryName()).' failed.')
                    : $response;
            }

            $lastReason = $response->errorMessage ?: $this->providerLabel($providerName).' failed.';
        }

        return new AIResponse(
            success: false,
            provider: 'none',
            model: null,
            content: 'AI service is temporarily unavailable. Please try again later.',
            fallbackUsed: true,
            errorMessage: 'AI service is temporarily unavailable.',
            rawMetadata: ['fallback_reason' => $lastReason],
        );
    }

    protected function primaryProvider(): AIProviderInterface
    {
        return $this->provider($this->primaryName()) ?? $this->ollamaProvider;
    }

    protected function provider(string $name): ?AIProviderInterface
    {
        return match ($name) {
            'openai' => $this->openAIProvider ??= app(OpenAIProvider::class),
            'gemini' => $this->geminiProvider ??= app(GeminiProvider::class),
            'ollama' => $this->ollamaProvider,
            'fake' => $this->fakeProvider,
            default => null,
        };
    }

    protected function providerChain(): array
    {
        $primary = $this->primaryName();
        $configured = config('ai.fallback_chain');

        if (is_string($configured) && trim($configured) !== '') {
            $chain = array_map('trim', explode(',', $configured));
        } elseif (is_array($configured)) {
            $chain = $configured;
        } elseif (in_array($primary, ['openai', 'gemini'], true)) {
            $chain = [$primary, 'ollama', 'fake'];
        } else {
            $chain = [$primary, (string) config('ai.fallback_provider', 'fake')];
        }

        return collect(array_merge([$primary], $chain))
            ->map(fn ($provider) => strtolower(trim((string) $provider)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function primaryName(): string
    {
        return strtolower(trim((string) config('ai.provider', 'openai'))) ?: 'openai';
    }

    protected function providerLabel(string $provider): string
    {
        return match ($provider) {
            'openai' => 'OpenAI',
            'gemini' => 'Gemini',
            'ollama' => 'Ollama',
            'fake' => 'Fake provider',
            default => ucfirst($provider),
        };
    }

    protected function markFallback(AIResponse $response, string $reason): AIResponse
    {
        return new AIResponse(
            success: $response->success,
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
