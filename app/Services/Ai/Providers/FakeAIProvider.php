<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;

class FakeAIProvider implements AIProviderInterface
{
    public function generate(AIRequest $request): AIResponse
    {
        $started = hrtime(true);
        $content = match ($request->feature) {
            'book_discovery_intent' => $this->intent($request->prompt),
            'book_discovery_recommendations' => $this->recommendations($request),
            'customer_support' => 'AI-generated response: PageTurner support can help with catalog browsing, cart, checkout, orders, receipts, and account questions.',
            default => 'AI-generated response: This is a deterministic offline response from FakeAIProvider.',
        };

        return new AIResponse(
            success: true,
            provider: $this->name(),
            model: 'fake-deterministic',
            content: $content,
            confidence: 0.65,
            tokensInput: str_word_count($request->prompt),
            tokensOutput: str_word_count(strip_tags($content)),
            latencyMs: (int) ((hrtime(true) - $started) / 1000000),
            rawMetadata: [
                'feature' => $request->feature,
                'mode' => 'offline',
            ],
        );
    }

    public function name(): string
    {
        return 'fake';
    }

    protected function intent(string $prompt): string
    {
        $text = strtolower($prompt);
        $category = $this->firstMatch($text, ['fiction', 'technology', 'business', 'science', 'education', 'history']);
        $format = $this->firstMatch($text, ['paperback', 'hardcover', 'ebook', 'audiobook']);
        $topic = $this->firstMatch($text, ['laravel', 'database', 'programming', 'php', 'api', 'study', 'history', 'physics', 'biology']);

        return json_encode([
            'mood' => $this->firstMatch($text, ['inspiring', 'relaxing', 'serious', 'practical', 'beginner']),
            'topic' => $topic,
            'category' => $category,
            'learning_goal' => str_contains($text, 'learn') || str_contains($text, 'beginner') ? $prompt : null,
            'format' => $format,
            'price_preference' => str_contains($text, 'under') || str_contains($text, 'cheap') ? 'budget' : null,
            'support_intent' => str_contains($text, 'order') || str_contains($text, 'checkout') ? 'customer_support' : 'book_discovery',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function recommendations(AIRequest $request): string
    {
        $books = collect($request->context['candidate_books'] ?? []);
        $limit = (int) ($request->context['limit'] ?? config('ai.recommendation_limit', 5));
        $prompt = strtolower($request->prompt);

        $recommendations = $books
            ->map(function (array $book) use ($prompt) {
                $haystack = strtolower(implode(' ', array_filter([
                    $book['title'] ?? '',
                    $book['author'] ?? '',
                    $book['description'] ?? '',
                    $book['category'] ?? '',
                    $book['category_slug'] ?? '',
                ])));

                $score = 0;
                foreach (preg_split('/[^a-z0-9]+/', $prompt) ?: [] as $term) {
                    if (strlen($term) >= 4 && str_contains($haystack, $term)) {
                        $score += 5;
                    }
                }

                if (($book['stock'] ?? 0) > 0) {
                    $score += 2;
                }

                return [
                    'book_id' => (int) ($book['id'] ?? 0),
                    'score' => $score,
                    'reason' => 'Matches the request using PageTurner catalog data.',
                ];
            })
            ->filter(fn (array $item) => $item['book_id'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $item) => [
                'book_id' => $item['book_id'],
                'reason' => $item['reason'],
            ])
            ->values()
            ->all();

        $titles = collect($recommendations)
            ->map(fn (array $item) => $books->firstWhere('id', $item['book_id'])['title'] ?? null)
            ->filter()
            ->take(3)
            ->implode(', ');

        return json_encode([
            'answer' => $titles !== ''
                ? "AI-generated response: These PageTurner books best match your request: {$titles}."
                : 'AI-generated response: I could not find a strong match in the active catalog.',
            'recommendations' => $recommendations,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function firstMatch(string $text, array $values): ?string
    {
        foreach ($values as $value) {
            if (str_contains($text, $value)) {
                return $value;
            }
        }

        return null;
    }
}
