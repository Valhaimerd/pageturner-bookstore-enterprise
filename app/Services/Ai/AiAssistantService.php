<?php

namespace App\Services\Ai;

use App\Models\AiInteraction;
use App\Repositories\BookRepository;
use App\Services\AI\AIServiceManager;
use App\Services\AI\DTOs\AIRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AiAssistantService
{
    public function __construct(
        protected AIServiceManager $ai,
        protected BookRepository $books,
        protected BookRecommendationRanker $ranker,
    ) {}

    public function respond(string $prompt, ?Request $request = null): array
    {
        $started = hrtime(true);
        $providerName = (string) config('ai.provider', 'ollama');
        $fallbackName = (string) config('ai.fallback_provider', 'fake');
        $fallbackUsed = false;
        $fallbackReason = null;
        $status = 'completed';
        $usage = [];
        $metadata = [];

        $intentResponse = $this->ai->generateWithFallback(new AIRequest(
            prompt: $prompt,
            systemPrompt: 'Return only valid JSON with keys: mood, topic, category, learning_goal, format, price_preference, support_intent. Use null for unknown values.',
            context: ['expect_json' => true],
            feature: 'book_discovery_intent',
            userId: $request?->user()?->id,
            conversationId: $request && $request->hasSession() ? $request->session()->getId() : null,
        ));

        $intent = json_decode($intentResponse->content, true);
        if (! is_array($intent)) {
            $intent = $this->ranker->extractIntent($prompt);
        }

        $intent = array_filter($intent, fn ($value) => $value !== '');

        if ($intentResponse->fallbackUsed) {
            $fallbackUsed = true;
            $fallbackReason = $intentResponse->errorMessage;
            $status = 'fallback';
        }

        $candidates = $this->books->recommendationCandidates(
            $intent,
            $prompt,
            (int) config('ai.candidate_limit', 12)
        );

        $candidatePayload = $this->candidatePayload($candidates);

        $recommendationResponse = $this->ai->generateWithFallback(new AIRequest(
            prompt: $prompt,
            systemPrompt: 'You recommend only books from the provided candidates. Return only valid JSON: {"answer":"short helpful answer","recommendations":[{"book_id":123,"reason":"short reason"}]}. Do not invent IDs.',
            context: [
                'expect_json' => true,
                'intent' => $intent,
                'limit' => (int) config('ai.recommendation_limit', 5),
                'candidate_books' => $candidatePayload,
            ],
            feature: 'book_discovery_recommendations',
            userId: $request?->user()?->id,
            conversationId: $request && $request->hasSession() ? $request->session()->getId() : null,
        ));

        $payload = json_decode($recommendationResponse->content, true);
        $recommendations = $this->validRecommendations($payload['recommendations'] ?? [], $candidates);
        $answer = is_array($payload) ? (string) ($payload['answer'] ?? '') : '';

        if ($recommendationResponse->fallbackUsed) {
            $fallbackUsed = true;
            $fallbackReason = $fallbackReason ?: $recommendationResponse->errorMessage;
            $status = 'fallback';
        }

        if ($recommendations === []) {
            $recommendations = $this->ranker->rank($candidates, $prompt, $intent, (int) config('ai.recommendation_limit', 5));
        }

        if ($answer === '') {
            $answer = $this->ranker->answer($candidates, $recommendations);
        }

        $usage = [
            'prompt_tokens' => $recommendationResponse->tokensInput ?? $intentResponse->tokensInput,
            'completion_tokens' => $recommendationResponse->tokensOutput ?? $intentResponse->tokensOutput,
            'total_tokens' => ($recommendationResponse->tokensInput ?? 0) + ($recommendationResponse->tokensOutput ?? 0),
        ];
        $metadata = [
            'intent_provider' => $intentResponse->provider,
            'recommendation_provider' => $recommendationResponse->provider,
            'intent_metadata' => $intentResponse->rawMetadata,
            'recommendation_metadata' => $recommendationResponse->rawMetadata,
        ];

        $recommendedIds = collect($recommendations)->pluck('book_id')->values()->all();
        $recommendedBooks = $candidates->whereIn('id', $recommendedIds)->values();

        $interaction = AiInteraction::create([
            'user_id' => $request?->user()?->id,
            'session_id' => $request && $request->hasSession() ? $request->session()->getId() : null,
            'provider' => $providerName,
            'fallback_provider' => $fallbackUsed ? $fallbackName : null,
            'model' => $recommendationResponse->model ?? $intentResponse->model,
            'prompt' => $prompt,
            'normalized_intent' => $intent,
            'candidate_book_ids' => $candidates->pluck('id')->values()->all(),
            'recommended_book_ids' => $recommendedIds,
            'answer' => $answer,
            'status' => $status,
            'fallback_used' => $fallbackUsed,
            'fallback_reason' => $fallbackReason,
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'latency_ms' => (int) ((hrtime(true) - $started) / 1000000),
            'estimated_cost_cents' => 0,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);

        return [
            'interaction' => $interaction,
            'answer' => $answer,
            'intent' => $intent,
            'fallback_used' => $fallbackUsed,
            'fallback_reason' => $fallbackReason,
            'recommendations' => $this->recommendationPayload($recommendedBooks, $recommendations),
        ];
    }

    protected function candidatePayload(Collection $books): array
    {
        return $books->map(fn ($book) => [
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'publisher' => $book->publisher,
            'format' => $book->format,
            'isbn' => $book->isbn,
            'description' => $book->description,
            'price' => (float) $book->price,
            'stock' => (int) $book->stock,
            'status' => $book->status,
            'category' => $book->category?->name,
            'category_slug' => $book->category?->slug,
            'average_rating' => $book->reviews_avg_rating ? round((float) $book->reviews_avg_rating, 1) : null,
        ])->values()->all();
    }

    protected function validRecommendations(array $recommendations, Collection $candidates): array
    {
        $candidateIds = $candidates->pluck('id')->map(fn ($id) => (int) $id)->all();

        return collect($recommendations)
            ->filter(fn ($item) => is_array($item) && isset($item['book_id']) && in_array((int) $item['book_id'], $candidateIds, true))
            ->map(fn ($item) => [
                'book_id' => (int) $item['book_id'],
                'reason' => trim((string) ($item['reason'] ?? 'Recommended from the active PageTurner catalog.')),
            ])
            ->unique('book_id')
            ->values()
            ->all();
    }

    protected function recommendationPayload(Collection $books, array $recommendations): array
    {
        $reasons = collect($recommendations)->keyBy('book_id');

        return $books->map(fn ($book) => [
            'id' => $book->id,
            'slug' => $book->slug,
            'title' => $book->title,
            'author' => $book->author,
            'publisher' => $book->publisher,
            'format' => $book->format,
            'isbn' => $book->isbn,
            'description' => $book->description,
            'price' => (float) $book->price,
            'stock' => (int) $book->stock,
            'status' => $book->status,
            'category' => [
                'name' => $book->category?->name,
                'slug' => $book->category?->slug,
            ],
            'average_rating' => $book->reviews_avg_rating ? round((float) $book->reviews_avg_rating, 1) : null,
            'reason' => $reasons->get($book->id)['reason'] ?? 'Recommended from the active PageTurner catalog.',
        ])->values()->all();
    }
}
