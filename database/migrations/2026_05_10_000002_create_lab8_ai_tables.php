<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('title')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('session_id');
            $table->index('status');
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('ai_conversation_id');
            $table->index('user_id');
            $table->index('role');
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('feature');
            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->boolean('success')->default(true);
            $table->string('error_code')->nullable();
            $table->decimal('cost_estimate', 10, 6)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('provider');
            $table->index('feature');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('ai_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature');
            $table->string('action');
            $table->string('input_hash');
            $table->string('output_hash')->nullable();
            $table->string('provider')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('risk_level')->default('low');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('feature');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_events');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
