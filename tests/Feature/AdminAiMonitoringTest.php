<?php

namespace Tests\Feature;

use App\Models\AiInteraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_ai_monitoring_dashboard_and_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $interaction = AiInteraction::create([
            'user_id' => null,
            'provider' => 'ollama',
            'fallback_provider' => 'fake',
            'model' => 'llama3.2:latest',
            'prompt' => 'Recommend a Laravel book.',
            'normalized_intent' => ['topic' => 'laravel'],
            'candidate_book_ids' => [1, 2],
            'recommended_book_ids' => [1],
            'answer' => 'Try Laravel for Builders.',
            'status' => 'fallback',
            'fallback_used' => true,
            'fallback_reason' => 'Ollama timeout.',
            'latency_ms' => 25,
            'estimated_cost_cents' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai.index'))
            ->assertOk()
            ->assertSee('AI Monitoring')
            ->assertSee('Fallbacks')
            ->assertSee('Ollama');

        $this->actingAs($admin)
            ->get(route('admin.ai.show', $interaction))
            ->assertOk()
            ->assertSee('AI Interaction #'.$interaction->id)
            ->assertSee('Ollama timeout')
            ->assertSee('Try Laravel for Builders');
    }
}
