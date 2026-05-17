<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiProvider implements AIProviderInterface
{
    public function generate(AIRequest $request): AIResponse
    {
        $started = hrtime(true);
        $apiKey = (string) config('ai.gemini.api_key', '');

        if (trim($apiKey) === '') {
            return $this->failed('Gemini API key is not configured.', $started, [
                'error_code' => 'provider_unconfigured',
            ]);
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('ai.gemini.timeout', 20))
                ->post($this->endpoint(), $this->payload($request));
        } catch (Throwable) {
            return $this->failed('Gemini is unavailable.', $started, [
                'error_code' => 'provider_unavailable',
            ]);
        }

        if ($response->status() === 429) {
            return $this->failed('Gemini rate limit exceeded.', $started, [
                'error_code' => 'rate_limited',
                'http_status' => 429,
            ]);
        }

        if ($response->serverError()) {
            return $this->failed('Gemini service is unavailable.', $started, [
                'error_code' => 'provider_unavailable',
                'http_status' => $response->status(),
            ]);
        }

        if (! $response->successful()) {
            return $this->failed('Gemini returned HTTP '.$response->status().'.', $started, [
                'error_code' => 'provider_http_error',
                'http_status' => $response->status(),
            ]);
        }

        $payload = $response->json();
        $content = $this->content($payload);

        if (trim($content) === '') {
            return $this->failed('Gemini returned an empty response.', $started, $payload);
        }

        if (($request->context['expect_json'] ?? false) === true && ! is_array(json_decode($content, true))) {
            return $this->failed('Gemini returned invalid JSON.', $started, $payload);
        }

        return new AIResponse(
            success: true,
            provider: $this->name(),
            model: $this->model(),
            content: trim($content),
            confidence: 0.82,
            tokensInput: $this->intValue(data_get($payload, 'usageMetadata.promptTokenCount')),
            tokensOutput: $this->intValue(data_get($payload, 'usageMetadata.candidatesTokenCount')),
            latencyMs: $this->latency($started),
            rawMetadata: $this->metadata($payload),
        );
    }

    public function name(): string
    {
        return 'gemini';
    }

    protected function payload(AIRequest $request): array
    {
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $this->userText($request),
                ]],
            ]],
        ];

        if (trim($request->systemPrompt) !== '') {
            $payload['systemInstruction'] = [
                'parts' => [[
                    'text' => $request->systemPrompt,
                ]],
            ];
        }

        if (($request->context['expect_json'] ?? false) === true) {
            $payload['generationConfig'] = [
                'responseMimeType' => 'application/json',
            ];
        }

        $maxOutputTokens = (int) config('ai.gemini.max_output_tokens', 0);
        if ($maxOutputTokens > 0) {
            $payload['generationConfig']['maxOutputTokens'] = $maxOutputTokens;
        }

        return $payload;
    }

    protected function userText(AIRequest $request): string
    {
        $content = $request->prompt;

        if ($request->context !== []) {
            $content .= "\n\nContext JSON:\n".json_encode(
                $this->safeContext($request->context),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        return $content;
    }

    protected function safeContext(array $context): array
    {
        unset($context['api_key'], $context['authorization'], $context['token'], $context['password'], $context['secret']);

        return $context;
    }

    protected function content(array $payload): string
    {
        $parts = [];

        foreach ((array) data_get($payload, 'candidates', []) as $candidate) {
            foreach ((array) data_get($candidate, 'content.parts', []) as $part) {
                if (is_string($part['text'] ?? null)) {
                    $parts[] = $part['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    protected function failed(string $message, int $started, array $metadata = []): AIResponse
    {
        return new AIResponse(
            success: false,
            provider: $this->name(),
            model: $this->model(),
            content: '',
            latencyMs: $this->latency($started),
            errorMessage: $message,
            rawMetadata: $this->metadata($metadata),
        );
    }

    protected function metadata(array $payload): array
    {
        return array_filter([
            'error_code' => $payload['error_code'] ?? null,
            'http_status' => $payload['http_status'] ?? null,
            'finish_reason' => data_get($payload, 'candidates.0.finishReason'),
            'usage_prompt_tokens' => data_get($payload, 'usageMetadata.promptTokenCount'),
            'usage_output_tokens' => data_get($payload, 'usageMetadata.candidatesTokenCount'),
            'usage_total_tokens' => data_get($payload, 'usageMetadata.totalTokenCount'),
        ], fn ($value) => $value !== null);
    }

    protected function endpoint(): string
    {
        return rtrim((string) config('ai.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/')
            .'/models/'.$this->model().':generateContent';
    }

    protected function model(): string
    {
        return (string) config('ai.gemini.model', 'gemini-2.5-flash');
    }

    protected function intValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected function latency(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1000000);
    }
}
