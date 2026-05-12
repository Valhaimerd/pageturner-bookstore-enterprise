<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;

interface AIProviderInterface
{
    public function generate(AIRequest $request): AIResponse;

    public function name(): string;
}
