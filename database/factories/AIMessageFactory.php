<?php

namespace Database\Factories;

use App\Models\AIConversation;
use App\Models\AIMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AIMessage>
 */
class AIMessageFactory extends Factory
{
    protected $model = AIMessage::class;

    public function definition(): array
    {
        return [
            'ai_conversation_id' => AIConversation::factory(),
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement(['user', 'assistant', 'system']),
            'content' => $this->faker->paragraph(),
            'provider' => 'fake',
            'model' => 'fake-deterministic',
            'confidence' => 0.6500,
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
