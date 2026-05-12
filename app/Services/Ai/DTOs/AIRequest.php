<?php

namespace App\Services\AI\DTOs;

class AIRequest
{
    public function __construct(
        public readonly string $prompt,
        public readonly string $systemPrompt = '',
        public readonly array $context = [],
        public readonly string $feature = 'general',
        public readonly ?int $userId = null,
        public readonly ?string $conversationId = null,
    ) {}

    public function withPrompt(string $prompt): self
    {
        return new self(
            prompt: $prompt,
            systemPrompt: $this->systemPrompt,
            context: $this->context,
            feature: $this->feature,
            userId: $this->userId,
            conversationId: $this->conversationId,
        );
    }
}
