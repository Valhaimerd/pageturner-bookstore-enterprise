<?php

namespace App\Data\Ai;

class AiProviderResult
{
    public function __construct(
        public readonly array $intent = [],
        public readonly array $recommendations = [],
        public readonly ?string $answer = null,
        public readonly array $usage = [],
        public readonly array $metadata = [],
    ) {}

    public static function intent(array $intent, array $usage = [], array $metadata = []): self
    {
        return new self(intent: $intent, usage: $usage, metadata: $metadata);
    }

    public static function recommendations(array $recommendations, string $answer, array $usage = [], array $metadata = []): self
    {
        return new self(recommendations: $recommendations, answer: $answer, usage: $usage, metadata: $metadata);
    }
}
