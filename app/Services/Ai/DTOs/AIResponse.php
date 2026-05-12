<?php

namespace App\Services\AI\DTOs;

class AIResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $provider,
        public readonly ?string $model,
        public readonly string $content,
        public readonly float $confidence = 0.0,
        public readonly ?int $tokensInput = null,
        public readonly ?int $tokensOutput = null,
        public readonly int $latencyMs = 0,
        public readonly bool $fallbackUsed = false,
        public readonly ?string $errorMessage = null,
        public readonly array $rawMetadata = [],
    ) {}

    public function withFallbackUsed(bool $fallbackUsed): self
    {
        return new self(
            success: $this->success,
            provider: $this->provider,
            model: $this->model,
            content: $this->content,
            confidence: $this->confidence,
            tokensInput: $this->tokensInput,
            tokensOutput: $this->tokensOutput,
            latencyMs: $this->latencyMs,
            fallbackUsed: $fallbackUsed,
            errorMessage: $this->errorMessage,
            rawMetadata: $this->rawMetadata,
        );
    }
}
