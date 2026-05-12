<?php

namespace Tests\Feature;

use App\Models\AIAuditEvent;
use App\Models\AIUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAIMonitoringDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_lab_eight_ai_monitoring_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.ai-monitoring.index'))
            ->assertOk()
            ->assertSee('Lab 8 AI Monitoring')
            ->assertSee('AI Calls Today')
            ->assertSee('Estimated Cost Total');
    }

    public function test_guest_cannot_access_lab_eight_ai_monitoring_dashboard(): void
    {
        $this->get(route('admin.ai-monitoring.index'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_lab_eight_ai_monitoring_dashboard(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->get(route('admin.ai-monitoring.index'))
            ->assertForbidden();
    }

    public function test_dashboard_works_with_no_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.ai-monitoring.index'))
            ->assertOk()
            ->assertSee('No provider usage yet')
            ->assertSee('No feature usage yet')
            ->assertSee('No AI usage logs recorded yet.')
            ->assertSee('No AI audit events recorded yet.')
            ->assertSee('&#8369;0.00', false);
    }

    public function test_dashboard_renders_fake_seeded_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        AIUsageLog::factory()->create([
            'user_id' => $admin->id,
            'provider' => 'ollama',
            'model' => 'llama3.2:latest',
            'feature' => 'book_discovery_recommendations',
            'latency_ms' => 100,
            'fallback_used' => false,
            'success' => true,
            'cost_estimate' => 0,
            'metadata' => ['mode' => 'local'],
        ]);

        AIUsageLog::factory()->create([
            'user_id' => $admin->id,
            'provider' => 'fake',
            'model' => 'fake-deterministic',
            'feature' => 'book_discovery_recommendations',
            'latency_ms' => 50,
            'fallback_used' => true,
            'success' => true,
            'cost_estimate' => 0,
            'metadata' => ['fallback_reason' => 'Ollama failed'],
        ]);

        AIUsageLog::factory()->create([
            'user_id' => $admin->id,
            'provider' => 'fake',
            'model' => 'fake-deterministic',
            'feature' => 'customer_support',
            'latency_ms' => null,
            'fallback_used' => false,
            'success' => false,
            'error_code' => 'ai_unavailable',
            'cost_estimate' => 0,
            'metadata' => ['error_code' => 'ai_unavailable'],
        ]);

        AIAuditEvent::factory()->create([
            'user_id' => $admin->id,
            'feature' => 'book_discovery_recommendations',
            'action' => 'recommendation_generated',
            'provider' => 'ollama',
            'input_hash' => str_repeat('a', 64),
            'output_hash' => str_repeat('b', 64),
            'risk_level' => 'low',
            'confidence' => 0.8,
            'metadata' => ['success' => true],
        ]);

        AIAuditEvent::factory()->create([
            'user_id' => $admin->id,
            'feature' => 'book_discovery_recommendations',
            'action' => 'fallback_triggered',
            'provider' => 'fake',
            'input_hash' => str_repeat('c', 64),
            'output_hash' => str_repeat('d', 64),
            'risk_level' => 'medium',
            'confidence' => 0.65,
            'metadata' => ['fallback_used' => true],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-monitoring.index'))
            ->assertOk()
            ->assertSee('AI Calls All Time')
            ->assertSee('3')
            ->assertSee('2 / 1')
            ->assertSee('Fallback Used')
            ->assertSee('1')
            ->assertSee('Ollama Calls')
            ->assertSee('Fake Fallback Calls')
            ->assertSee('75ms')
            ->assertSee('Ollama')
            ->assertSee('Fake')
            ->assertSee('book discovery recommendations')
            ->assertSee('customer support')
            ->assertSee('recommendation generated')
            ->assertSee('fallback triggered')
            ->assertSee(str_repeat('a', 16))
            ->assertSee(str_repeat('c', 16))
            ->assertSee('&#8369;0.00', false);
    }

    public function test_admin_navigation_includes_ai_monitoring_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.ai-monitoring.index'))
            ->assertOk()
            ->assertSee('AI Monitoring')
            ->assertSee(route('admin.ai-monitoring.index'), false);
    }
}
