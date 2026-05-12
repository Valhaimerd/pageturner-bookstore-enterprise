<?php

namespace App\Services\Ai;

use App\Contracts\Ai\AiProvider;
use App\Data\Ai\AiProviderResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OllamaAiProvider implements AiProvider
{
    public function extractIntent(string $prompt): AiProviderResult
    {
        $payload = $this->chat([
            [
                'role' => 'system',
                'content' => 'Return only valid JSON with keys: mood, topic, category, learning_goal, format, price_preference, support_intent. Use null for unknown values.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ]);

        return AiProviderResult::intent($payload, $this->usageFrom($payload), ['model' => $this->model()]);
    }

    public function recommendBooks(string $prompt, array $intent, array $books, int $limit): AiProviderResult
    {
        $payload = $this->chat([
            [
                'role' => 'system',
                'content' => 'You recommend only books from the provided candidates. Return only valid JSON: {"answer":"short helpful answer","recommendations":[{"book_id":123,"reason":"short reason"}]}. Do not invent IDs.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'prompt' => $prompt,
                    'intent' => $intent,
                    'limit' => $limit,
                    'candidate_books' => $books,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        ]);

        if (! isset($payload['recommendations']) || ! is_array($payload['recommendations'])) {
            throw new RuntimeException('Ollama response did not include recommendations.');
        }

        return AiProviderResult::recommendations(
            $payload['recommendations'],
            (string) ($payload['answer'] ?? 'Here are the best matching books from the PageTurner catalog.'),
            $this->usageFrom($payload),
            ['model' => $this->model()]
        );
    }

    protected function chat(array $messages): array
    {
        try {
            $response = Http::timeout((int) config('ai.ollama.timeout', 15))
                ->post($this->baseUrl().'/api/chat', [
                    'model' => $this->model(),
                    'messages' => $messages,
                    'stream' => false,
                    'format' => 'json',
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Ollama is unavailable: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Ollama returned HTTP '.$response->status().'.');
        }

        $content = data_get($response->json(), 'message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Ollama returned an empty response.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Ollama returned invalid JSON.');
        }

        return $decoded;
    }

    protected function usageFrom(array $payload): array
    {
        return [
            'prompt_tokens' => $payload['prompt_tokens'] ?? null,
            'completion_tokens' => $payload['completion_tokens'] ?? null,
            'total_tokens' => $payload['total_tokens'] ?? null,
        ];
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('ai.ollama.base_url'), '/');
    }

    protected function model(): string
    {
        return (string) config('ai.ollama.model', 'llama3.2:latest');
    }
}
