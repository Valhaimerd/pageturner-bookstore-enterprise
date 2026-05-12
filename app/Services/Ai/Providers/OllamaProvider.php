<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaProvider implements AIProviderInterface
{
    public function generate(AIRequest $request): AIResponse
    {
        $started = hrtime(true);

        try {
            $response = Http::timeout((int) config('ai.ollama.timeout', 15))
                ->post($this->baseUrl().'/api/chat', [
                    'model' => $this->model(),
                    'messages' => $this->messages($request),
                    'stream' => false,
                ]);
        } catch (Throwable) {
            return $this->failed('Ollama is unavailable.', $started);
        }

        if (! $response->successful()) {
            return $this->failed('Ollama returned an invalid response.', $started);
        }

        $payload = $response->json();
        $content = data_get($payload, 'message.content');

        if (! is_string($content) || trim($content) === '') {
            return $this->failed('Ollama returned an empty response.', $started, $payload);
        }

        if (($request->context['expect_json'] ?? false) === true && ! is_array(json_decode($content, true))) {
            return $this->failed('Ollama returned invalid JSON.', $started, $payload);
        }

        return new AIResponse(
            success: true,
            provider: $this->name(),
            model: $this->model(),
            content: trim($content),
            confidence: 0.80,
            tokensInput: is_numeric($payload['prompt_eval_count'] ?? null) ? (int) $payload['prompt_eval_count'] : null,
            tokensOutput: is_numeric($payload['eval_count'] ?? null) ? (int) $payload['eval_count'] : null,
            latencyMs: $this->latency($started),
            rawMetadata: $this->metadata($payload),
        );
    }

    public function name(): string
    {
        return 'ollama';
    }

    protected function messages(AIRequest $request): array
    {
        $messages = [];

        if (trim($request->systemPrompt) !== '') {
            $messages[] = [
                'role' => 'system',
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
        unset($context['api_key'], $context['token'], $context['password'], $context['secret']);

        return $context;
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
            'done' => $payload['done'] ?? null,
            'total_duration' => $payload['total_duration'] ?? null,
            'load_duration' => $payload['load_duration'] ?? null,
            'prompt_eval_count' => $payload['prompt_eval_count'] ?? null,
            'eval_count' => $payload['eval_count'] ?? null,
            'eval_duration' => $payload['eval_duration'] ?? null,
        ], fn ($value) => $value !== null);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('ai.ollama.base_url', 'http://127.0.0.1:11434'), '/');
    }

    protected function model(): string
    {
        return (string) config('ai.ollama.model', 'llama3.2');
    }

    protected function latency(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1000000);
    }
}
