<?php

namespace App\Services\Ai;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BookRecommendationRanker
{
    public function extractIntent(string $prompt): array
    {
        $text = Str::lower($prompt);

        return [
            'mood' => $this->firstMatch($text, ['relaxing', 'inspiring', 'serious', 'practical', 'beginner', 'advanced', 'suspense']),
            'topic' => $this->topicFromText($text),
            'category' => $this->firstMatch($text, ['fiction', 'technology', 'business', 'science', 'education', 'history']),
            'learning_goal' => $this->containsAny($text, ['learn', 'study', 'exam', 'beginner', 'guide']) ? $prompt : null,
            'format' => $this->firstMatch($text, ['paperback', 'hardcover', 'ebook', 'audiobook']),
            'price_preference' => $this->containsAny($text, ['cheap', 'budget', 'affordable', 'low price']) ? 'budget' : null,
            'support_intent' => $this->containsAny($text, ['order', 'checkout', 'cart', 'support', 'help']) ? 'customer_support' : 'book_discovery',
        ];
    }

    public function rank(Collection $books, string $prompt, array $intent, int $limit): array
    {
        $terms = $this->terms($prompt, $intent);

        return $books
            ->map(function ($book) use ($terms, $intent) {
                $haystack = Str::lower(implode(' ', array_filter([
                    $book->title,
                    $book->author,
                    $book->publisher,
                    $book->format,
                    $book->description,
                    $book->category?->name,
                ])));

                $score = 0;

                foreach ($terms as $term) {
                    if ($term !== '' && str_contains($haystack, $term)) {
                        $score += 5;
                    }
                }

                if (($intent['category'] ?? null) && Str::lower((string) $book->category?->slug) === Str::lower((string) $intent['category'])) {
                    $score += 12;
                }

                if (($intent['format'] ?? null) && Str::lower((string) $book->format) === Str::lower((string) $intent['format'])) {
                    $score += 4;
                }

                if ((bool) $book->is_featured) {
                    $score += 2;
                }

                if ((int) $book->stock > 0) {
                    $score += 2;
                }

                $score += min(3, (float) ($book->reviews_avg_rating ?? 0));

                return [
                    'book_id' => $book->id,
                    'score' => $score,
                    'reason' => $this->reasonFor($book, $intent),
                ];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    public function answer(Collection $books, array $recommendations): string
    {
        if ($books->isEmpty() || $recommendations === []) {
            return 'I could not find a strong match in the current active catalog. Try a broader topic, category, mood, or learning goal.';
        }

        $titles = collect($recommendations)
            ->map(fn (array $item) => $books->firstWhere('id', $item['book_id'])?->title)
            ->filter()
            ->take(3)
            ->implode(', ');

        return "Here are PageTurner books that best match your request: {$titles}.";
    }

    protected function reasonFor($book, array $intent): string
    {
        if (($intent['category'] ?? null) && $book->category?->slug === $intent['category']) {
            return 'Matches the requested category and is available in the active catalog.';
        }

        if (($intent['learning_goal'] ?? null)) {
            return 'Relevant to the learning goal described in your question.';
        }

        return 'A close match based on title, description, category, and availability.';
    }

    protected function terms(string $prompt, array $intent): array
    {
        $words = collect(preg_split('/[^a-z0-9]+/i', $prompt) ?: [])
            ->map(fn (string $word) => Str::lower($word))
            ->filter(fn (string $word) => strlen($word) >= 4)
            ->reject(fn (string $word) => in_array($word, ['book', 'books', 'recommend', 'please', 'want', 'need', 'about'], true));

        return $words
            ->merge(array_filter([
                $intent['mood'] ?? null,
                $intent['topic'] ?? null,
                $intent['category'] ?? null,
                $intent['format'] ?? null,
            ]))
            ->unique()
            ->values()
            ->all();
    }

    protected function topicFromText(string $text): ?string
    {
        foreach (['laravel', 'database', 'marketing', 'physics', 'biology', 'history', 'study', 'startup', 'api', 'code'] as $topic) {
            if (str_contains($text, $topic)) {
                return $topic;
            }
        }

        return null;
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

    protected function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
