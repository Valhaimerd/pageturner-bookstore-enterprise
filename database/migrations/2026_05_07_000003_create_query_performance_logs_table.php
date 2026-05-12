<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_performance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('benchmark');
            $table->unsignedInteger('iterations');
            $table->decimal('average_ms', 10, 3);
            $table->decimal('min_ms', 10, 3);
            $table->decimal('max_ms', 10, 3);
            $table->decimal('total_ms', 12, 3);
            $table->decimal('target_ms', 10, 3)->nullable();
            $table->boolean('passed')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['benchmark', 'created_at']);
            $table->index(['passed', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_performance_logs');
    }
};
