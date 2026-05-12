<?php

return [
    'enabled' => filter_var(env('AI_ENABLED', true), FILTER_VALIDATE_BOOL),
    'provider' => env('AI_PROVIDER', 'ollama'),
    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'fake'),

    'max_prompt_chars' => (int) env('AI_MAX_PROMPT_CHARS', 4000),
    'candidate_limit' => (int) env('AI_CANDIDATE_LIMIT', 12),
    'recommendation_limit' => (int) env('AI_RECOMMENDATION_LIMIT', 5),
    'queue_summaries' => filter_var(env('AI_QUEUE_SUMMARIES', false), FILTER_VALIDATE_BOOL),

    'ollama' => [
        'base_url' => rtrim((string) env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:latest'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 15),
    ],
];
