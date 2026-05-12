<?php

namespace App\Services\AI;

use App\Models\Book;
use App\Repositories\BookRepository;
use App\Services\AI\DTOs\AIRequest;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class BookDiscoveryAIService
{
    public function __construct(
        protected BookRepository $books,
        protected AIServiceManager $ai,
        protected ?AISafetyService $safety = null,
    ) {}

    public function recommend(
        string $question,
        ?int $userId = null,
        ?string $conversationId = null,
        array $filters = []
    ): array {
        $started = hrtime(true);
        $question = trim($question);
        $intent = $this->intent($question, $filters);
        $candidates = $this->retrieveCandidates($intent, $question, $filters);
        $context = $this->bookContext($candidates);

        if ($candidates->isEmpty()) {
            return [
                'answer' => 'I could not find active in-stock books matching your request. Try a broader topic or category.',
                'recommendations' => [],
                'provider_used' => 'local',
                'fallback_used' => true,
                'confidence' => 0.0,
                'latency_ms' => $this->latency($started),
                'needs_human_help' => true,
                'error_message' => 'No eligible books found.',
            ];
        }

        $response = $this->ai->generateWithFallback(new AIRequest(
            prompt: $question,
            systemPrompt: $this->systemPrompt(),
            context: [
                'expect_json' => true,
                'candidate_books' => $context,
                'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
                'limit' => min(5, $candidates->count()),
                'expected_schema' => [
                    'answer' => 'short answer',
                    'recommendations' => [
                        [
                            'book_id' => 1,
                            'title' => 'Book title',
                            'reason' => 'Why this book matches',
                            'confidence' => 0.85,
                        ],
                    ],
                    'follow_up_question' => 'optional question',
                    'confidence' => 0.85,
                    'needs_human_help' => false,
                ],
            ],
            feature: 'book_discovery_recommendations',
            userId: $userId,
            conversationId: $conversationId,
        ));

        if (! $response->success && ($response->rawMetadata['error_code'] ?? null) === 'unsafe_input_blocked') {
            return [
                'answer' => $this->safety()->sanitizeOutputText($response->content),
                'recommendations' => [],
                'provider_used' => $response->provider,
                'fallback_used' => false,
                'confidence' => 0.0,
                'latency_ms' => $this->latency($started),
                'needs_human_help' => true,
                'error_message' => $response->errorMessage,
                'follow_up_question' => null,
            ];
        }

        $payload = $this->parseJson($response->content);
        $usedDeterministicFallback = false;

        if ($payload === null) {
            $payload = $this->deterministicPayload($question, $candidates);
            $usedDeterministicFallback = true;
        }

        $recommendations = $this->validRecommendations($payload['recommendations'] ?? [], $candidates);

        if ($recommendations->isEmpty()) {
            $payload = $this->deterministicPayload($question, $candidates);
            $recommendations = $this->validRecommendations($payload['recommendations'] ?? [], $candidates);
            $usedDeterministicFallback = true;
        }

        $confidence = $this->confidence($payload, $recommendations);
        $needsHumanHelp = (bool) ($payload['needs_human_help'] ?? false) || $confidence < 0.5;

        return [
            'answer' => $this->safety()->sanitizeOutputText((string) ($payload['answer'] ?? 'Here are matching books from the PageTurner catalog.')),
            'recommendations' => $recommendations->values()->all(),
            'provider_used' => $usedDeterministicFallback ? 'local' : $response->provider,
            'fallback_used' => $response->fallbackUsed || $usedDeterministicFallback,
            'confidence' => $confidence,
            'latency_ms' => $this->latency($started),
            'needs_human_help' => $needsHumanHelp,
            'error_message' => $usedDeterministicFallback ? 'AI response was invalid or unusable; deterministic recommendations were used.' : $response->errorMessage,
            'follow_up_question' => isset($payload['follow_up_question'])
                ? $this->safety()->sanitizeOutputText((string) $payload['follow_up_question'])
                : null,
        ];
    }

    protected function retrieveCandidates(array $intent, string $question, array $filters): EloquentCollection
    {
        $candidates = $this->books->recommendationCandidates($intent, $question, 10);

        return $candidates
            ->filter(fn (Book $book) => $book->status === 'active' && (int) $book->stock > 0)
            ->when($filters['max_price'] ?? null, fn (Collection $books, $maxPrice) => $books
                ->filter(fn (Book $book) => (float) $book->price <= (float) $maxPrice))
            ->when($filters['author'] ?? null, fn (Collection $books, $author) => $books
                ->filter(fn (Book $book) => str_contains(mb_strtolower($book->author), mb_strtolower((string) $author))))
            ->take(10)
            ->values();
    }

    protected function intent(string $question, array $filters): array
    {
        return array_filter([
            'category' => $filters['category'] ?? null,
            'mood' => $filters['mood'] ?? null,
            'topic' => $filters['topic'] ?? $this->firstMatch($question, ['laravel', 'database', 'programming', 'php', 'api', 'science', 'history', 'study']),
            'learning_goal' => $filters['learning_goal'] ?? (str_contains(mb_strtolower($question), 'learn') ? $question : null),
            'format' => $filters['format'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected function bookContext(EloquentCollection|Collection $books): array
    {
        return $books->map(fn (Book $book) => [
            'id' => $book->id,
            'slug' => $book->slug,
            'title' => $book->title,
            'author' => $book->author,
            'price' => (float) $book->price,
            'stock' => (int) $book->stock,
            'category' => $book->category?->name,
            'category_slug' => $book->category?->slug,
            'description' => $book->description,
            'status' => $book->status,
            'publisher' => $book->publisher,
            'format' => $book->format,
        ])->values()->all();
    }

    protected function systemPrompt(): string
    {
        return $this->safety()->systemPromptForBookAssistant()."\nReturn JSON only using this shape: ".
            '{"answer":"short answer","recommendations":[{"book_id":1,"title":"Book title","reason":"Why this book matches","confidence":0.85}],"follow_up_question":null,"confidence":0.85,"needs_human_help":false}'.
            "\nMention unavailable or low stock when relevant. Explain why each recommendation matches the customer request. If the request is vague, include a follow_up_question.";
    }

    protected function parseJson(string $content): ?array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $content, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function validRecommendations(array $recommendations, EloquentCollection|Collection $candidates): Collection
    {
        $candidateIds = $candidates->pluck('id')->map(fn ($id) => (int) $id)->all();
        $recommendations = $this->safety()->validateRecommendationIds($recommendations, $candidateIds);

        return collect($recommendations)
            ->map(function (array $item) use ($candidates) {
                $book = $candidates->firstWhere('id', (int) $item['book_id']);

                return [
                    'book' => $book,
                    'book_id' => $book->id,
                    'title' => $book->title,
                    'reason' => $this->safety()->sanitizeOutputText((string) ($item['reason'] ?? 'Recommended from the active PageTurner catalog.')),
                    'confidence' => max(0.0, min(1.0, (float) ($item['confidence'] ?? 0.6))),
                ];
            })
            ->unique('book_id')
            ->values();
    }

    protected function deterministicPayload(string $question, EloquentCollection|Collection $candidates): array
    {
        $terms = collect(preg_split('/[^a-z0-9]+/i', $question) ?: [])
            ->map(fn (string $term) => mb_strtolower($term))
            ->filter(fn (string $term) => mb_strlen($term) >= 4)
            ->values();

        $recommendations = $candidates
            ->map(function (Book $book) use ($terms) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $book->title,
                    $book->author,
                    $book->publisher,
                    $book->format,
                    $book->description,
                    $book->category?->name,
                ])));

                $score = 0;
                foreach ($terms as $term) {
                    if (str_contains($haystack, $term)) {
                        $score += 1;
                    }
                }

                return [
                    'book_id' => $book->id,
                    'title' => $book->title,
                    'reason' => 'Matches the request using PageTurner catalog data.',
                    'confidence' => $score > 0 ? 0.65 : 0.45,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->take(5)
            ->map(fn (array $item) => [
                'book_id' => $item['book_id'],
                'title' => $item['title'],
                'reason' => $item['reason'],
                'confidence' => $item['confidence'],
            ])
            ->values()
            ->all();

        return [
            'answer' => 'Here are matching books from the active PageTurner catalog.',
            'recommendations' => $recommendations,
            'follow_up_question' => $terms->isEmpty() ? 'What topic, mood, author, or learning goal should I focus on?' : null,
            'confidence' => collect($recommendations)->avg('confidence') ?: 0.0,
            'needs_human_help' => false,
        ];
    }

    protected function confidence(array $payload, Collection $recommendations): float
    {
        if (isset($payload['confidence']) && is_numeric($payload['confidence'])) {
            return max(0.0, min(1.0, (float) $payload['confidence']));
        }

        return max(0.0, min(1.0, (float) ($recommendations->avg('confidence') ?: 0.0)));
    }

    protected function firstMatch(string $text, array $values): ?string
    {
        $text = mb_strtolower($text);

        foreach ($values as $value) {
            if (str_contains($text, $value)) {
                return $value;
            }
        }

        return null;
    }

    protected function latency(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1000000);
    }

    protected function safety(): AISafetyService
    {
        return $this->safety ??= app(AISafetyService::class);
    }
}
