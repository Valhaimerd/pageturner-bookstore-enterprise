<?php

return [
    'enabled' => filter_var(env('AI_ENABLED', true), FILTER_VALIDATE_BOOL),
    'provider' => env('AI_PROVIDER', 'openai'),
    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'fake'),
    'fallback_chain' => env('AI_FALLBACK_CHAIN'),

    'max_prompt_chars' => (int) env('AI_MAX_PROMPT_CHARS', 4000),
    'candidate_limit' => (int) env('AI_CANDIDATE_LIMIT', 12),
    'recommendation_limit' => (int) env('AI_RECOMMENDATION_LIMIT', 5),
    'queue_summaries' => filter_var(env('AI_QUEUE_SUMMARIES', false), FILTER_VALIDATE_BOOL),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 20),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 1200),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => rtrim((string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 20),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 1200),
    ],

    'ollama' => [
        'base_url' => rtrim((string) env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:latest'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 15),
    ],
];
