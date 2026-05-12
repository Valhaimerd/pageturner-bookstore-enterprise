<?php

namespace Database\Factories;

use App\Models\AIAuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AIAuditEvent>
 */
class AIAuditEventFactory extends Factory
{
    protected $model = AIAuditEvent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'feature' => 'book_discovery',
            'action' => 'recommendation_generated',
            'input_hash' => hash('sha256', $this->faker->sentence()),
            'output_hash' => hash('sha256', $this->faker->sentence()),
            'provider' => 'fake',
            'confidence' => 0.6500,
            'risk_level' => 'low',
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
