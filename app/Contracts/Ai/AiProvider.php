<?php

namespace App\Contracts\Ai;

use App\Data\Ai\AiProviderResult;

interface AiProvider
{
    public function extractIntent(string $prompt): AiProviderResult;

    public function recommendBooks(string $prompt, array $intent, array $books, int $limit): AiProviderResult;
}
