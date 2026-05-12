<?php

namespace App\Services\Ai;

use App\Contracts\Ai\AiProvider;
use App\Data\Ai\AiProviderResult;
use Illuminate\Support\Collection;

class FakeAiProvider implements AiProvider
{
    public function __construct(protected BookRecommendationRanker $ranker) {}

    public function extractIntent(string $prompt): AiProviderResult
    {
        return AiProviderResult::intent(
            $this->ranker->extractIntent($prompt),
            metadata: ['provider_mode' => 'deterministic']
        );
    }

    public function recommendBooks(string $prompt, array $intent, array $books, int $limit): AiProviderResult
    {
        $collection = new Collection($books);
        $recommendations = $this->ranker->rank($collection, $prompt, $intent, $limit);

        return AiProviderResult::recommendations(
            $recommendations,
            $this->ranker->answer($collection, $recommendations),
            metadata: ['provider_mode' => 'deterministic']
        );
    }
}
