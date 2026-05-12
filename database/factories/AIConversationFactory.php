<?php

namespace Database\Factories;

use App\Models\AIConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AIConversation>
 */
class AIConversationFactory extends Factory
{
    protected $model = AIConversation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_id' => $this->faker->uuid(),
            'title' => $this->faker->sentence(4),
            'status' => 'active',
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
