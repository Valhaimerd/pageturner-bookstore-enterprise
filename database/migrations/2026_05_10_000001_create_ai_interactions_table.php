<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('provider');
            $table->string('fallback_provider')->nullable();
            $table->string('model')->nullable();
            $table->text('prompt');
            $table->json('normalized_intent')->nullable();
            $table->json('candidate_book_ids')->nullable();
            $table->json('recommended_book_ids')->nullable();
            $table->text('answer')->nullable();
            $table->string('status');
            $table->boolean('fallback_used')->default(false);
            $table->text('fallback_reason')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('estimated_cost_cents', 10, 2)->default(0);
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'status', 'created_at']);
            $table->index(['fallback_used', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};
