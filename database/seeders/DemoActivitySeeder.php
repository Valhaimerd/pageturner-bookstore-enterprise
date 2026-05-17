<?php

namespace Database\Seeders;

use App\Models\AIAuditEvent;
use App\Models\AIConversation;
use App\Models\AIMessage;
use App\Models\AIUsageLog;
use App\Models\AiInteraction;
use App\Models\ApiRateLimitHit;
use App\Models\Audit;
use App\Models\BackupMonitoring;
use App\Models\Book;
use App\Models\ExportLog;
use App\Models\ImportLog;
use App\Models\Order;
use App\Models\Review;
use App\Models\ScheduledTaskLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoActivitySeeder extends Seeder
{
    private const SOURCE = 'demo_activity';

    public function run(): void
    {
        $users = User::query()->get()->keyBy('email');
        $admins = User::query()->where('role', 'admin')->get();
        $customers = User::query()->where('role', 'customer')->get();
        $books = Book::query()->orderBy('id')->get();
        $orders = Order::query()->orderBy('placed_at')->get();
        $reviews = Review::query()->orderBy('id')->get();

        if ($users->isEmpty() || $admins->isEmpty()) {
            return;
        }

        \Illuminate\Database\Eloquent\Model::unguarded(function () use ($users, $admins, $customers, $books, $orders, $reviews): void {
            $this->clearDemoActivity();
            $this->seedFiles();

            $admin = $admins->first();

            ModelWithoutEvents::run(fn () => $this->seedOperationalLogs($admin, $customers, $orders));
            $this->seedNotifications($admins, $customers, $orders, $reviews);
            $this->seedAudits($admin, $users, $books, $orders, $reviews);
            $this->seedAiActivity($customers, $books);
            $this->seedQueryPerformanceLogs();
        });
    }

    private function clearDemoActivity(): void
    {
        Storage::disk('local')->deleteDirectory('demo-activity');

        DB::table('audits')->where('tags', self::SOURCE)->delete();
        DB::table('notifications')->where('data', 'like', '%"source":"'.self::SOURCE.'"%')->delete();

        $this->deleteRowsByJsonSource('scheduled_task_logs', 'metadata');
        $this->deleteRowsByJsonSource('backup_monitoring', 'metadata');
        $this->deleteRowsByJsonSource('import_logs', 'metadata');
        $this->deleteRowsByJsonSource('export_logs', 'filters');
        $this->deleteRowsByJsonSource('api_rate_limit_hits', 'metadata');
        $this->deleteRowsByJsonSource('ai_interactions', 'metadata');
        $this->deleteRowsByJsonSource('ai_usage_logs', 'metadata');
        $this->deleteRowsByJsonSource('ai_audit_events', 'metadata');
        $this->deleteRowsByJsonSource('ai_messages', 'metadata');

        $conversationIds = DB::table('ai_conversations')
            ->where('metadata->source', self::SOURCE)
            ->pluck('id');

        if ($conversationIds->isNotEmpty()) {
            DB::table('ai_messages')->whereIn('ai_conversation_id', $conversationIds)->delete();
            DB::table('ai_usage_logs')->whereIn('ai_conversation_id', $conversationIds)->delete();
            DB::table('ai_conversations')->whereIn('id', $conversationIds)->delete();
        }

        $this->deleteRowsByJsonSource('query_performance_logs', 'metadata');
    }

    private function deleteRowsByJsonSource(string $table, string $column): void
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->where("{$column}->source", self::SOURCE)->delete();
        }
    }

    private function seedFiles(): void
    {
        Storage::disk('local')->put(
            'demo-activity/exports/books-inventory.csv',
            "isbn,title,stock\n9781000000002,Laravel for Builders,25\n"
        );
        Storage::disk('local')->put(
            'demo-activity/exports/orders-summary.csv',
            "order_number,status,total\nORD-DEMO-0001,completed,1569.00\n"
        );
        Storage::disk('local')->put(
            'demo-activity/exports/revenue-summary.csv',
            "period,gross_revenue,orders\nlast_30_days,7354.00,10\n"
        );
        Storage::disk('local')->put(
            'demo-activity/exports/tax-report.csv',
            "period,tax_collected,taxable_orders\nlast_30_days,0.00,10\n"
        );
        Storage::disk('local')->put(
            'demo-activity/import-failures/books-import-failures.json',
            json_encode([
                ['row' => 7, 'errors' => ['Duplicate ISBN skipped.']],
                ['row' => 12, 'errors' => ['Invalid stock value.']],
            ], JSON_PRETTY_PRINT)
        );
    }

    private function seedOperationalLogs(User $admin, $customers, $orders): void
    {
        $this->seedScheduledTaskLogs();
        $this->seedBackupMonitoring($admin);
        $this->seedImportLogs($admin);
        $this->seedExportLogs($admin, $customers, $orders);
        $this->seedApiRateLimitHits($admin, $customers);
    }

    private function seedScheduledTaskLogs(): void
    {
        $tasks = [
            ['app:backup-run daily', 'success', 'Daily backup completed.', 4210, 1],
            ['app:backup-run weekly', 'success', 'Weekly full backup completed.', 9360, 7],
            ['backup:clean', 'success', 'Old backup archives pruned.', 1880, 1],
            ['backup:monitor', 'success', 'Latest backup health check passed.', 960, 1],
            ['backup:weekly-summary', 'success', 'Weekly backup summary generated.', 740, 2],
            ['order:cleanup-pending', 'success', 'Expired pending orders reviewed.', 1320, 1],
            ['audit:archive', 'success', 'Older audit rows marked for archival.', 2140, 3],
            ['notifications:prune', 'success', 'Read notifications older than retention were pruned.', 680, 4],
            ['logs:rotate', 'success', 'Application log rotation completed.', 510, 1],
            ['system:health-check', 'failed', 'Storage warning detected during health check.', 350, 0],
        ];

        foreach ($tasks as [$task, $status, $output, $runtime, $daysAgo]) {
            $startedAt = now()->subDays($daysAgo)->setTime(2, 0)->addMinutes($runtime % 47);
            ScheduledTaskLog::create([
                'task' => $task,
                'status' => $status,
                'description' => $this->taskDescription($task),
                'output' => $output,
                'runtime_ms' => $runtime,
                'metadata' => ['source' => self::SOURCE, 'environment' => 'demo-production'],
                'started_at' => $startedAt,
                'finished_at' => $startedAt->copy()->addMilliseconds($runtime),
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);
        }
    }

    private function taskDescription(string $task): string
    {
        return match (true) {
            str_contains($task, 'backup') => 'Backup and recovery evidence task.',
            str_contains($task, 'audit') => 'Audit compliance maintenance task.',
            str_contains($task, 'order') => 'Order lifecycle maintenance task.',
            default => 'Scheduled production operations task.',
        };
    }

    private function seedBackupMonitoring(User $admin): void
    {
        $events = [
            ['daily_backup', 'success', 'local', 'backups/pageturner/daily-2026-05-14.zip', 48123904, 'Daily backup completed successfully.', 1],
            ['weekly_backup', 'success', 'local', 'backups/pageturner/weekly-2026-05-10.zip', 80211922, 'Weekly full backup completed successfully.', 5],
            ['manual_backup', 'failed', null, null, null, 'pg_dump was not found. Install PostgreSQL client tools or add pg_dump to PATH.', 0],
            ['backup_cleanup', 'success', 'local', null, null, 'Backup cleanup removed expired archives.', 1],
            ['backup_health', 'success', 'local', null, null, 'Healthy backup was found on the configured disk.', 1],
            ['weekly_summary', 'success', null, null, null, 'Weekly backup summary notification generated.', 2],
        ];

        foreach ($events as [$event, $status, $disk, $path, $size, $message, $daysAgo]) {
            $happenedAt = now()->subDays($daysAgo)->setTime(3, 15);
            BackupMonitoring::create([
                'initiated_by_user_id' => $event === 'manual_backup' ? $admin->id : null,
                'event' => $event,
                'status' => $status,
                'disk' => $disk,
                'path' => $path,
                'size_bytes' => $size,
                'message' => $message,
                'metadata' => ['source' => self::SOURCE, 'scope' => Str::before($event, '_')],
                'happened_at' => $happenedAt,
                'created_at' => $happenedAt,
                'updated_at' => $happenedAt,
            ]);
        }
    }

    private function seedImportLogs(User $admin): void
    {
        $imports = [
            ['books', 'spring-inventory-books.csv', 'completed', 'update_existing', 54, 54, 52, 2, 'demo-activity/import-failures/books-import-failures.json', 6],
            ['users', 'customer-profile-cleanup.csv', 'completed', 'skip', 18, 18, 18, 0, null, 9],
            ['books', 'supplier-preview-invalid.csv', 'failed', 'skip', 24, 0, 0, 24, 'demo-activity/import-failures/books-import-failures.json', 12],
        ];

        foreach ($imports as [$type, $filename, $status, $strategy, $total, $processed, $success, $failed, $failurePath, $daysAgo]) {
            $completedAt = now()->subDays($daysAgo)->setTime(11, 20);
            ImportLog::create([
                'user_id' => $admin->id,
                'type' => $type,
                'filename' => $filename,
                'disk' => 'local',
                'path' => "demo-activity/imports/{$filename}",
                'status' => $status,
                'duplicate_strategy' => $strategy,
                'total_rows' => $total,
                'processed_rows' => $processed,
                'success_rows' => $success,
                'failed_rows' => $failed,
                'failure_report_path' => $failurePath,
                'metadata' => ['source' => self::SOURCE, 'batch' => 'operations-demo'],
                'completed_at' => $completedAt,
                'created_at' => $completedAt->copy()->subMinutes(4),
                'updated_at' => $completedAt,
            ]);
        }
    }

    private function seedExportLogs(User $admin, $customers, $orders): void
    {
        $customer = $customers->first();
        $exports = [
            [$admin->id, 'books', 'csv', 'completed', 'demo-activity/exports/books-inventory.csv', 15, ['category' => 'all'], 2],
            [$admin->id, 'orders', 'csv', 'completed', 'demo-activity/exports/orders-summary.csv', $orders->count(), ['status' => 'all'], 1],
            [$admin->id, 'revenue_summary', 'csv', 'completed', 'demo-activity/exports/revenue-summary.csv', max(1, $orders->count()), ['date_range' => 'last_30_days'], 1],
            [$admin->id, 'tax_report', 'csv', 'completed', 'demo-activity/exports/tax-report.csv', max(1, $orders->count()), ['date_range' => 'last_30_days'], 1],
            [$customer?->id, 'customer_orders', 'csv', 'completed', 'demo-activity/exports/orders-summary.csv', $customer?->orders()->count() ?? 0, ['owner' => 'customer'], 3],
        ];

        foreach ($exports as [$userId, $type, $format, $status, $path, $rows, $filters, $daysAgo]) {
            $completedAt = now()->subDays($daysAgo)->setTime(15, 45);
            ExportLog::create([
                'user_id' => $userId,
                'type' => $type,
                'format' => $format,
                'status' => $status,
                'disk' => 'local',
                'path' => $path,
                'total_rows' => $rows,
                'filters' => ['source' => self::SOURCE] + $filters,
                'columns' => ['id', 'status', 'created_at'],
                'completed_at' => $completedAt,
                'expires_at' => $completedAt->copy()->addDays(7),
                'created_at' => $completedAt->copy()->subMinutes(2),
                'updated_at' => $completedAt,
            ]);
        }
    }

    private function seedApiRateLimitHits(User $admin, $customers): void
    {
        $rows = [
            [null, 'public', '/api/v1/books', 'GET', 60, 54, null, 200, false, 0],
            [$customers->first()?->id, 'standard', '/api/v1/orders', 'GET', 120, 98, null, 200, false, 0],
            [$customers->where('subscription_tier', 'premium')->first()?->id, 'premium', '/api/v1/books', 'GET', 600, 481, null, 200, false, 1],
            [$admin->id, 'admin', '/api/v1/admin/reports', 'GET', 1000, 912, null, 200, false, 1],
            [$customers->first()?->id, 'auth', '/login', 'POST', 5, 0, 42, 429, true, 0],
            [null, 'public', '/api/v1/books', 'GET', 3, 0, 1, 429, true, 0],
        ];

        foreach ($rows as [$userId, $tier, $endpoint, $method, $limit, $remaining, $retryAfter, $status, $throttled, $daysAgo]) {
            ApiRateLimitHit::create([
                'user_id' => $userId,
                'tier' => $tier,
                'endpoint' => $endpoint,
                'method' => $method,
                'ip_address' => '127.0.0.1',
                'limit' => $limit,
                'remaining' => $remaining,
                'retry_after' => $retryAfter,
                'status_code' => $status,
                'throttled' => $throttled,
                'metadata' => ['source' => self::SOURCE, 'user_agent' => 'Demo Browser'],
                'created_at' => now()->subDays($daysAgo)->subMinutes($limit % 17),
            ]);
        }
    }

    private function seedNotifications($admins, $customers, $orders, $reviews): void
    {
        $admin = $admins->first();
        $customer = $customers->first();
        $premiumCustomer = $customers->where('subscription_tier', 'premium')->first() ?? $customer;
        $order = $orders->first();
        $review = $reviews->first();

        $notifications = array_filter([
            $order ? [$admin, 'App\\Notifications\\AdminNewOrderNotification', [
                'source' => self::SOURCE,
                'type' => 'admin_new_order',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_email' => $order->buyer_email,
                'message' => 'A new order has been placed.',
            ], null, 1] : null,
            $review ? [$admin, 'App\\Notifications\\AdminNewReviewNotification', [
                'source' => self::SOURCE,
                'type' => 'admin_new_review',
                'review_id' => $review->id,
                'book_id' => $review->book_id,
                'rating' => $review->rating,
                'message' => 'A new review was submitted.',
            ], now()->subHours(8), 2] : null,
            $order ? [$customer, 'App\\Notifications\\CustomerOrderPlacedNotification', [
                'source' => self::SOURCE,
                'type' => 'customer_order_placed',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'receipt_number' => $order->receipt_number,
                'message' => 'Your order has been placed successfully.',
            ], now()->subDays(1), 3] : null,
            $order ? [$premiumCustomer, 'App\\Notifications\\OrderStatusUpdatedNotification', [
                'source' => self::SOURCE,
                'type' => 'order_status_updated',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'message' => 'Your order status was updated.',
            ], null, 0] : null,
            [$admin, 'App\\Notifications\\SystemHealthNotification', [
                'source' => self::SOURCE,
                'type' => 'system_health',
                'status' => 'warning',
                'message' => 'Backup preflight warning requires PostgreSQL client tools.',
            ], null, 0],
        ]);

        foreach ($notifications as [$user, $type, $data, $readAt, $daysAgo]) {
            if (! $user) {
                continue;
            }

            $createdAt = now()->subDays($daysAgo)->subMinutes(22);
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'read_at' => $readAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function seedAudits(User $admin, $users, $books, $orders, $reviews): void
    {
        $book = $books->first();
        $order = $orders->first();
        $review = $reviews->first();
        $manager = $users->get('manager@pageturner.test') ?? $admin;
        $customer = $users->get('ria@pageturner.test') ?? $users->first();

        $records = array_filter([
            $book ? [$admin, $book, 'updated', ['stock' => 17], ['stock' => 25], '/admin/books/'.$book->id, 'PATCH', 5] : null,
            $book ? [$admin, $book, 'updated', ['price' => '779.00'], ['price' => '799.00'], '/admin/books/'.$book->id, 'PATCH', 3] : null,
            $order ? [$admin, $order, 'order_status_transition', ['status' => 'pending'], ['status' => $order->status, 'payment_status' => $order->payment_status], '/admin/orders/'.$order->id, 'PATCH', 2] : null,
            [$manager, $manager, 'two_factor_enabled', ['two_factor_enabled' => false], ['two_factor_enabled' => true], '/two-factor/enable', 'POST', 10],
            [$admin, $customer, 'subscription_tier_changed', ['subscription_tier' => 'standard'], ['subscription_tier' => $customer->subscription_tier], '/admin/users/'.$customer->id, 'PATCH', 4],
            $review ? [$admin, $review, 'review_moderated', ['is_visible' => false], ['is_visible' => true], '/admin/reviews/'.$review->id, 'PATCH', 6] : null,
            [$admin, $admin, 'admin_export_created', [], ['type' => 'revenue_summary', 'format' => 'csv'], '/admin/data-management/exports/orders', 'POST', 1],
            [$admin, $admin, 'manual_backup_failed', [], ['reason' => 'pg_dump_missing'], '/admin/data-management/backups/run', 'POST', 0],
        ]);

        foreach ($records as [$actor, $auditable, $event, $old, $new, $url, $method, $daysAgo]) {
            $this->createAudit($actor, $auditable, $event, $old, $new, $url, $method, now()->subDays($daysAgo)->subMinutes(7));
        }
    }

    private function createAudit(User $actor, object $auditable, string $event, array $old, array $new, string $url, string $method, Carbon $createdAt): void
    {
        $audit = new Audit();
        $audit->forceFill([
            'user_type' => User::class,
            'user_id' => $actor->id,
            'event' => $event,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->id,
            'old_values' => $old,
            'new_values' => $new + ['source' => self::SOURCE],
            'url' => url($url),
            'method' => $method,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Demo Activity Seeder',
            'tags' => self::SOURCE,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $audit->save();
    }

    private function seedAiActivity($customers, $books): void
    {
        $customer = $customers->first();
        $premiumCustomer = $customers->where('subscription_tier', 'premium')->first() ?? $customer;
        $recommended = $books->take(3)->pluck('id')->values()->all();

        if (! $customer) {
            return;
        }

        $conversation = AIConversation::create([
            'user_id' => $customer->id,
            'session_id' => (string) Str::uuid(),
            'title' => 'Laravel and clean code recommendations',
            'status' => 'active',
            'metadata' => ['source' => self::SOURCE, 'channel' => 'web-assistant'],
            'created_at' => now()->subHours(7),
            'updated_at' => now()->subHours(6),
        ]);

        AIMessage::create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => $customer->id,
            'role' => 'user',
            'content' => 'Can you recommend books about Laravel and cleaner code?',
            'metadata' => ['source' => self::SOURCE],
            'created_at' => now()->subHours(7),
            'updated_at' => now()->subHours(7),
        ]);

        AIMessage::create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => null,
            'role' => 'assistant',
            'content' => 'Laravel for Builders and Clean Code Essentials are strong matches.',
            'provider' => 'fake',
            'model' => 'fake-deterministic',
            'confidence' => 0.8700,
            'metadata' => ['source' => self::SOURCE, 'recommended_book_ids' => $recommended],
            'created_at' => now()->subHours(7)->addSeconds(8),
            'updated_at' => now()->subHours(7)->addSeconds(8),
        ]);

        $usageRows = [
            [$customer->id, $conversation->id, 'ollama', 'llama3.2', 'book_discovery', 62, 118, 842, false, true, null],
            [$premiumCustomer?->id, null, 'fake', 'fake-deterministic', 'order_help', 31, 74, 120, true, true, null],
            [$customer->id, $conversation->id, 'ollama', 'llama3.2', 'conversation_summary', 140, 52, 1510, false, false, 'provider_timeout'],
        ];

        foreach ($usageRows as [$userId, $conversationId, $provider, $model, $feature, $input, $output, $latency, $fallback, $success, $error]) {
            AIUsageLog::create([
                'user_id' => $userId,
                'ai_conversation_id' => $conversationId,
                'provider' => $provider,
                'model' => $model,
                'feature' => $feature,
                'tokens_input' => $input,
                'tokens_output' => $output,
                'latency_ms' => $latency,
                'fallback_used' => $fallback,
                'success' => $success,
                'error_code' => $error,
                'cost_estimate' => 0,
                'metadata' => ['source' => self::SOURCE, 'zero_cost_provider' => true],
                'created_at' => now()->subHours($fallback ? 5 : 7),
                'updated_at' => now()->subHours($fallback ? 5 : 7),
            ]);
        }

        AiInteraction::create([
            'user_id' => $customer->id,
            'session_id' => $conversation->session_id,
            'provider' => 'fake',
            'fallback_provider' => 'fake',
            'model' => 'fake-deterministic',
            'prompt' => 'Find beginner friendly API design books.',
            'normalized_intent' => ['topic' => 'api design', 'level' => 'beginner'],
            'candidate_book_ids' => $books->pluck('id')->take(5)->values()->all(),
            'recommended_book_ids' => $recommended,
            'answer' => 'API Design Handbook is the best match for practical API design.',
            'status' => 'fallback',
            'fallback_used' => true,
            'fallback_reason' => 'Local provider unavailable during demo preflight.',
            'prompt_tokens' => 38,
            'completion_tokens' => 61,
            'total_tokens' => 99,
            'latency_ms' => 186,
            'estimated_cost_cents' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Demo Browser',
            'metadata' => ['source' => self::SOURCE],
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);

        foreach (['recommendation_generated', 'fallback_used', 'safety_checked'] as $index => $action) {
            AIAuditEvent::create([
                'user_id' => $customer->id,
                'feature' => 'book_discovery',
                'action' => $action,
                'input_hash' => hash('sha256', self::SOURCE.'input'.$action),
                'output_hash' => hash('sha256', self::SOURCE.'output'.$action),
                'provider' => $action === 'fallback_used' ? 'fake' : 'ollama',
                'confidence' => $action === 'safety_checked' ? 0.9900 : 0.8700,
                'risk_level' => $action === 'fallback_used' ? 'medium' : 'low',
                'metadata' => ['source' => self::SOURCE, 'sequence' => $index + 1],
                'created_at' => now()->subHours(7)->addMinutes($index),
                'updated_at' => now()->subHours(7)->addMinutes($index),
            ]);
        }
    }

    private function seedQueryPerformanceLogs(): void
    {
        $benchmarks = [
            ['catalog_cursor_pagination', 100, 12.420, 9.118, 19.774, 1242.000, 50.000, true],
            ['api_books_field_filtering', 100, 8.337, 6.901, 13.004, 833.700, 50.000, true],
            ['book_keyword_search', 75, 18.672, 13.445, 31.228, 1400.400, 75.000, true],
            ['bestseller_dashboard_widget', 50, 24.115, 18.882, 41.390, 1205.750, 100.000, true],
            ['large_order_export_query', 20, 96.350, 80.225, 135.881, 1927.000, 100.000, true],
        ];

        foreach ($benchmarks as [$name, $iterations, $average, $min, $max, $total, $target, $passed]) {
            DB::table('query_performance_logs')->insert([
                'benchmark' => $name,
                'iterations' => $iterations,
                'average_ms' => $average,
                'min_ms' => $min,
                'max_ms' => $max,
                'total_ms' => $total,
                'target_ms' => $target,
                'passed' => $passed,
                'metadata' => json_encode(['source' => self::SOURCE, 'dataset' => 'demo']),
                'created_at' => now()->subDays($iterations % 6)->subMinutes($iterations % 13),
            ]);
        }
    }
}

class ModelWithoutEvents
{
    public static function run(callable $callback): mixed
    {
        return ImportLog::withoutEvents(fn () => ExportLog::withoutEvents(fn () => BackupMonitoring::withoutEvents(fn () => $callback())));
    }
}
