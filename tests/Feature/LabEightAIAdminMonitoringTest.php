<?php

namespace Tests\Feature;

use App\Models\AIAuditEvent;
use App\Models\AIUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabEightAIAdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_ai_monitoring_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.ai-monitoring.index'))
            ->assertOk()
            ->assertSee('Lab 8 AI Monitoring')
            ->assertSee('AI Calls Today')
            ->assertSee('Estimated Cost Total');
    }

    public function test_customer_cannot_view_ai_monitoring_dashboard(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get(route('admin.ai-monitoring.index'))
            ->assertForbidden();
    }

    public function test_admin_dashboard_shows_seeded_usage_and_audit_evidence(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        AIUsageLog::factory()->create([
            'user_id' => $admin->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'feature' => 'book_discovery_recommendations',
            'success' => true,
            'fallback_used' => false,
            'latency_ms' => 90,
            'cost_estimate' => 0,
        ]);

        AIUsageLog::factory()->create([
            'user_id' => $admin->id,
            'provider' => 'ollama',
            'model' => 'llama3.2:latest',
            'feature' => 'book_discovery_recommendations',
            'success' => true,
            'fallback_used' => false,
            'latency_ms' => 100,
            'cost_estimate' => 0,
        ]);

        AIUsageLog::factory()->create([
            'user_id' => $admin->id,
            'provider' => 'fake',
            'model' => 'fake-deterministic',
            'feature' => 'summarization',
            'success' => true,
            'fallback_used' => true,
            'latency_ms' => 50,
            'cost_estimate' => 0,
        ]);

        AIAuditEvent::factory()->create([
            'user_id' => $admin->id,
            'feature' => 'summarization',
            'action' => 'chat_response_generated',
            'provider' => 'fake',
            'input_hash' => str_repeat('e', 64),
            'output_hash' => str_repeat('f', 64),
            'risk_level' => 'low',
            'metadata' => ['fallback_used' => true],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-monitoring.index'))
            ->assertOk()
            ->assertSee('3')
            ->assertSee('OpenAI')
            ->assertSee('Ollama')
            ->assertSee('Fake')
            ->assertSee('summarization')
            ->assertSee('chat response generated')
            ->assertSee(str_repeat('e', 16))
            ->assertSee('&#8369;0.00', false);
    }
}
