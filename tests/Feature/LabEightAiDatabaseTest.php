<?php

namespace Tests\Feature;

use App\Models\AIAuditEvent;
use App\Models\AIConversation;
use App\Models\AIMessage;
use App\Models\AIUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LabEightAiDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_eight_ai_tables_are_created(): void
    {
        $this->assertTrue(Schema::hasTable('ai_conversations'));
        $this->assertTrue(Schema::hasTable('ai_messages'));
        $this->assertTrue(Schema::hasTable('ai_usage_logs'));
        $this->assertTrue(Schema::hasTable('ai_audit_events'));
    }

    public function test_conversation_to_messages_relationship_works(): void
    {
        $conversation = AIConversation::factory()->create();
        $message = AIMessage::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'metadata' => ['safe' => true],
        ]);

        $this->assertTrue($conversation->messages->contains($message));
        $this->assertTrue($message->conversation->is($conversation));
        $this->assertSame(['safe' => true], $message->metadata);
    }

    public function test_usage_log_belongs_to_user_and_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = AIConversation::factory()->create(['user_id' => $user->id]);
        $usageLog = AIUsageLog::factory()->create([
            'user_id' => $user->id,
            'ai_conversation_id' => $conversation->id,
            'fallback_used' => true,
            'success' => false,
            'metadata' => ['error' => 'timeout'],
        ]);

        $this->assertTrue($usageLog->user->is($user));
        $this->assertTrue($usageLog->conversation->is($conversation));
        $this->assertTrue($usageLog->fallback_used);
        $this->assertFalse($usageLog->success);
        $this->assertSame(['error' => 'timeout'], $usageLog->metadata);
        $this->assertTrue($user->aiUsageLogs->contains($usageLog));
    }

    public function test_audit_event_belongs_to_user_and_casts_metadata(): void
    {
        $user = User::factory()->create();
        $event = AIAuditEvent::factory()->create([
            'user_id' => $user->id,
            'metadata' => ['risk_reason' => 'catalog-only response'],
        ]);

        $this->assertTrue($event->user->is($user));
        $this->assertSame(['risk_reason' => 'catalog-only response'], $event->metadata);
        $this->assertTrue($user->aiAuditEvents->contains($event));
    }

    public function test_conversation_metadata_casts_and_soft_deletes(): void
    {
        $conversation = AIConversation::factory()->create([
            'metadata' => ['feature' => 'book_discovery'],
        ]);

        $this->assertSame(['feature' => 'book_discovery'], $conversation->metadata);

        $conversation->delete();

        $this->assertSoftDeleted('ai_conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_nullable_user_foreign_keys_allow_guest_ai_records(): void
    {
        $conversation = AIConversation::factory()->create([
            'user_id' => null,
            'session_id' => 'guest-session',
        ]);
        $message = AIMessage::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => null,
        ]);
        $usageLog = AIUsageLog::factory()->create([
            'user_id' => null,
            'ai_conversation_id' => $conversation->id,
        ]);
        $auditEvent = AIAuditEvent::factory()->create([
            'user_id' => null,
        ]);

        $this->assertNull($conversation->user);
        $this->assertNull($message->user);
        $this->assertNull($usageLog->user);
        $this->assertNull($auditEvent->user);
    }
}
