<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('filename');
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->string('status')->default('queued');
            $table->string('duplicate_strategy')->default('skip');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('failure_report_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('format');
            $table->string('status')->default('processing');
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('scheduled_task_logs', function (Blueprint $table) {
            $table->id();
            $table->string('task');
            $table->string('status')->default('success');
            $table->string('description')->nullable();
            $table->longText('output')->nullable();
            $table->unsignedInteger('runtime_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['task', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('api_rate_limit_hits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tier');
            $table->string('endpoint');
            $table->string('method', 16);
            $table->ipAddress('ip_address')->nullable();
            $table->unsignedInteger('limit')->nullable();
            $table->unsignedInteger('remaining')->nullable();
            $table->unsignedInteger('retry_after')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->boolean('throttled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tier', 'created_at']);
            $table->index(['endpoint', 'created_at']);
            $table->index(['throttled', 'created_at']);
        });

        Schema::create('backup_monitoring', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->string('status');
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('happened_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'happened_at']);
            $table->index(['event', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_monitoring');
        Schema::dropIfExists('api_rate_limit_hits');
        Schema::dropIfExists('scheduled_task_logs');
        Schema::dropIfExists('export_logs');
        Schema::dropIfExists('import_logs');
    }
};
