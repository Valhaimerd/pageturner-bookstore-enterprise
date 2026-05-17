<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\ExportLog;
use App\Models\ImportLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoActivitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_adds_idempotent_demo_activity_without_extra_users(): void
    {
        Storage::fake('local');

        $this->seed(DatabaseSeeder::class);

        $userCount = User::count();
        $this->assertSame(10, $userCount);

        $firstCounts = $this->demoActivityCounts();

        foreach ($firstCounts as $table => $count) {
            $this->assertGreaterThan(0, $count, "Expected seeded demo activity rows for {$table}.");
        }

        $this->assertTrue(
            Audit::where('tags', 'demo_activity')
                ->get()
                ->every(fn (Audit $audit) => $audit->hasValidChecksum())
        );

        ExportLog::query()
            ->where('filters->source', 'demo_activity')
            ->where('status', 'completed')
            ->whereNotNull('path')
            ->pluck('path')
            ->each(fn (string $path) => Storage::disk('local')->assertExists($path));

        ImportLog::query()
            ->where('metadata->source', 'demo_activity')
            ->whereNotNull('failure_report_path')
            ->pluck('failure_report_path')
            ->each(fn (string $path) => Storage::disk('local')->assertExists($path));

        $userIds = User::pluck('id')->all();
        $notificationUserIds = DB::table('notifications')
            ->where('data', 'like', '%"source":"demo_activity"%')
            ->pluck('notifiable_id')
            ->all();

        $this->assertEmpty(array_diff($notificationUserIds, $userIds));

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($userCount, User::count());
        $this->assertSame($firstCounts, $this->demoActivityCounts());
    }

    private function demoActivityCounts(): array
    {
        return [
            'audits' => Audit::where('tags', 'demo_activity')->count(),
            'scheduled_task_logs' => DB::table('scheduled_task_logs')->where('metadata->source', 'demo_activity')->count(),
            'backup_monitoring' => DB::table('backup_monitoring')->where('metadata->source', 'demo_activity')->count(),
            'import_logs' => ImportLog::where('metadata->source', 'demo_activity')->count(),
            'export_logs' => ExportLog::where('filters->source', 'demo_activity')->count(),
            'api_rate_limit_hits' => DB::table('api_rate_limit_hits')->where('metadata->source', 'demo_activity')->count(),
            'notifications' => DB::table('notifications')->where('data', 'like', '%"source":"demo_activity"%')->count(),
            'ai_interactions' => DB::table('ai_interactions')->where('metadata->source', 'demo_activity')->count(),
            'ai_conversations' => DB::table('ai_conversations')->where('metadata->source', 'demo_activity')->count(),
            'ai_messages' => DB::table('ai_messages')->where('metadata->source', 'demo_activity')->count(),
            'ai_usage_logs' => DB::table('ai_usage_logs')->where('metadata->source', 'demo_activity')->count(),
            'ai_audit_events' => DB::table('ai_audit_events')->where('metadata->source', 'demo_activity')->count(),
            'query_performance_logs' => DB::table('query_performance_logs')->where('metadata->source', 'demo_activity')->count(),
        ];
    }
}
