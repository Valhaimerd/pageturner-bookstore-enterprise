<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAIProvider implements AIProviderInterface
{
    public function generate(AIRequest $request): AIResponse
    {
        $started = hrtime(true);
        $apiKey = (string) config('ai.openai.api_key', '');

        if (trim($apiKey) === '') {
            return $this->failed('OpenAI API key is not configured.', $started, [
                'error_code' => 'provider_unconfigured',
            ]);
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('ai.openai.timeout', 20))
                ->post($this->endpoint(), $this->payload($request));
        } catch (Throwable) {
            return $this->failed('OpenAI is unavailable.', $started, [
                'error_code' => 'provider_unavailable',
            ]);
        }

        if ($response->status() === 429) {
            return $this->failed('OpenAI rate limit exceeded.', $started, [
                'error_code' => 'rate_limited',
                'http_status' => 429,
            ]);
        }

        if ($response->serverError()) {
            return $this->failed('OpenAI service is unavailable.', $started, [
                'error_code' => 'provider_unavailable',
                'http_status' => $response->status(),
            ]);
        }

        if (! $response->successful()) {
            return $this->failed('OpenAI returned HTTP '.$response->status().'.', $started, [
                'error_code' => 'provider_http_error',
                'http_status' => $response->status(),
            ]);
        }

        $payload = $response->json();
        $content = $this->content($payload);

        if (trim($content) === '') {
            return $this->failed('OpenAI returned an empty response.', $started, $payload);
        }

        if (($request->context['expect_json'] ?? false) === true && ! is_array(json_decode($content, true))) {
            return $this->failed('OpenAI returned invalid JSON.', $started, $payload);
        }

        return new AIResponse(
            success: true,
            provider: $this->name(),
            model: (string) data_get($payload, 'model', $this->model()),
            content: trim($content),
            confidence: 0.85,
            tokensInput: $this->intValue(data_get($payload, 'usage.input_tokens')),
            tokensOutput: $this->intValue(data_get($payload, 'usage.output_tokens')),
            latencyMs: $this->latency($started),
            rawMetadata: $this->metadata($payload),
        );
    }

    public function name(): string
    {
        return 'openai';
    }

    protected function payload(AIRequest $request): array
    {
        $payload = [
            'model' => $this->model(),
            'input' => $this->messages($request),
        ];

        if (($request->context['expect_json'] ?? false) === true) {
            $payload['text'] = [
                'format' => [
                    'type' => 'json_object',
                ],
            ];
        }

        $maxOutputTokens = (int) config('ai.openai.max_output_tokens', 0);
        if ($maxOutputTokens > 0) {
            $payload['max_output_tokens'] = $maxOutputTokens;
        }

        return $payload;
    }

    protected function messages(AIRequest $request): array
    {
        $messages = [];

        if (trim($request->systemPrompt) !== '') {
            $messages[] = [
                'role' => 'developer',
                'content' => $request->systemPrompt,
            ];
        }

        $content = $request->prompt;

        if ($request->context !== []) {
            $content .= "\n\nContext JSON:\n".json_encode(
                $this->safeContext($request->context),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        $messages[] = [
            'role' => 'user',
            'content' => $content,
        ];

        return $messages;
    }

    protected function safeContext(array $context): array
    {
        unset($context['api_key'], $context['authorization'], $context['token'], $context['password'], $context['secret']);

        return $context;
    }

    protected function content(array $payload): string
    {
        if (is_string($payload['output_text'] ?? null)) {
            return $payload['output_text'];
        }

        $parts = [];

        foreach (($payload['output'] ?? []) as $output) {
            foreach (($output['content'] ?? []) as $content) {
                if (is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
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
            'response_id' => $payload['id'] ?? null,
            'status' => $payload['status'] ?? null,
            'error_code' => $payload['error_code'] ?? null,
            'http_status' => $payload['http_status'] ?? null,
            'usage_input_tokens' => data_get($payload, 'usage.input_tokens'),
            'usage_output_tokens' => data_get($payload, 'usage.output_tokens'),
            'usage_total_tokens' => data_get($payload, 'usage.total_tokens'),
        ], fn ($value) => $value !== null);
    }

    protected function endpoint(): string
    {
        return rtrim((string) config('ai.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses';
    }

    protected function model(): string
    {
        return (string) config('ai.openai.model', 'gpt-4o-mini');
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
