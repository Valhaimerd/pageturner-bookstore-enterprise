<?php

namespace Database\Factories;

use App\Models\AIConversation;
use App\Models\AIUsageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AIUsageLog>
 */
class AIUsageLogFactory extends Factory
{
    protected $model = AIUsageLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ai_conversation_id' => AIConversation::factory(),
            'provider' => 'fake',
            'model' => 'fake-deterministic',
            'feature' => 'book_discovery',
            'tokens_input' => $this->faker->numberBetween(1, 100),
            'tokens_output' => $this->faker->numberBetween(1, 100),
            'latency_ms' => $this->faker->numberBetween(1, 500),
            'fallback_used' => false,
            'success' => true,
            'error_code' => null,
            'cost_estimate' => 0,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
