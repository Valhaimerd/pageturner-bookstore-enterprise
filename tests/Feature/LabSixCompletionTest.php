<?php

namespace Tests\Feature;

use App\Console\Commands\AppBackupRun;
use App\Models\Audit;
use App\Models\Book;
use App\Models\Category;
use App\Models\ExportLog;
use App\Models\ImportLog;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class LabSixCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_import_missing_headers_fails_with_failure_report(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->createWithContent(
            'books.csv',
            implode("\n", [
                'isbn,title,author,price,stock,description',
                '9789715087228,Domain Driven Design,Eric Evans,1299.00,25,Sample description',
            ])
        );

        $this
            ->actingAs($admin)
            ->post(route('admin.data.imports.books'), [
                'file' => $file,
                'duplicate_strategy' => 'skip',
            ])
            ->assertSessionHasErrors('file');

        $log = ImportLog::firstOrFail();

        $this->assertSame('failed', $log->status);
        $this->assertSame(['category'], $log->metadata['missing_headers']);
        Storage::disk('local')->assertExists($log->failure_report_path);
    }

    public function test_book_import_rejects_blank_or_invalid_isbn_rows(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        Category::create(['name' => 'Technology', 'slug' => 'technology']);

        $file = UploadedFile::fake()->createWithContent(
            'books.csv',
            implode("\n", [
                'isbn,title,author,price,stock,category,description',
                ',No ISBN,Author,100.00,5,Technology,Missing ISBN',
                'invalid-isbn,Bad ISBN,Author,100.00,5,Technology,Invalid ISBN',
            ])
        );

        $this
            ->actingAs($admin)
            ->post(route('admin.data.imports.books'), [
                'file' => $file,
                'duplicate_strategy' => 'skip',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('books', 0);

        $log = ImportLog::firstOrFail();
        $this->assertSame('completed', $log->status);
        $this->assertSame(2, $log->failed_rows);
        Storage::disk('local')->assertExists($log->failure_report_path);
    }

    public function test_book_import_update_existing_duplicate_strategy_updates_existing_book(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        Book::create([
            'category_id' => $category->id,
            'title' => 'Old Title',
            'slug' => 'old-title',
            'author' => 'Old Author',
            'isbn' => '9789715087228',
            'price' => 100,
            'stock' => 1,
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'books.csv',
            implode("\n", [
                'isbn,title,author,price,stock,category,description',
                '9789715087228,Domain Driven Design,Eric Evans,1299.00,25,Technology,Updated description',
            ])
        );

        $this
            ->actingAs($admin)
            ->post(route('admin.data.imports.books'), [
                'file' => $file,
                'duplicate_strategy' => 'update_existing',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', [
            'isbn' => '9789715087228',
            'title' => 'Domain Driven Design',
            'stock' => 25,
        ]);
    }

    public function test_admin_can_export_revenue_summary_and_tax_report(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-FIN-1',
            'receipt_number' => 'RCT-FIN-1',
            'buyer_name' => $customer->name,
            'buyer_email' => $customer->email,
            'address_line_1' => '123 Main St',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
            'country' => 'Philippines',
            'subtotal' => 1000,
            'tax_amount' => 120,
            'total_amount' => 1120,
            'status' => 'completed',
            'payment_status' => 'paid',
            'placed_at' => now(),
        ]);

        foreach (['revenue_summary', 'tax_report'] as $reportType) {
            $this
                ->actingAs($admin)
                ->post(route('admin.data.exports.orders'), [
                    'format' => 'csv',
                    'financial_report_type' => $reportType,
                    'date_from' => now()->subDay()->toDateString(),
                    'date_to' => now()->toDateString(),
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect();

            $log = ExportLog::where('type', $reportType)->firstOrFail();
            $this->assertSame('completed', $log->status);
            $this->assertSame(1, $log->total_rows);
            Storage::disk('local')->assertExists($log->path);
        }
    }

    public function test_backup_wrapper_and_weekly_summary_write_monitoring_evidence(): void
    {
        $this->artisan('app:backup-run', ['scope' => 'daily', '--dry-run' => true])
            ->expectsOutput('Daily backup dry run completed.')
            ->assertSuccessful();

        $this->assertDatabaseHas('backup_monitoring', [
            'event' => 'daily_backup',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('scheduled_task_logs', [
            'task' => 'app:backup-run daily',
            'status' => 'success',
        ]);
        $successRuntime = DB::table('scheduled_task_logs')
            ->where('task', 'app:backup-run daily')
            ->value('runtime_ms');

        $this->assertIsInt($successRuntime);

        $this->artisan('backup:weekly-summary', ['--dry-run' => true])
            ->expectsOutput('Weekly backup summary dry run generated.')
            ->assertSuccessful();

        $this->assertDatabaseHas('backup_monitoring', [
            'event' => 'weekly_summary',
            'status' => 'success',
        ]);
    }

    public function test_backup_wrapper_preflight_records_failed_monitoring_when_pg_dump_is_missing(): void
    {
        $originalPath = getenv('PATH');
        putenv('PATH=');

        config([
            'backup.backup.source.databases' => ['pgsql'],
            'database.connections.pgsql.dump.dump_binary_path' => base_path('definitely-missing-pg-dump-folder'),
        ]);

        try {
            $this->artisan('app:backup-run', ['scope' => 'manual'])
                ->expectsOutput('pg_dump was not found on PATH or the configured dump path. Set PG_DUMP_BINARY_PATH (preferred) or PGSQL_DUMP_PATH to the PostgreSQL bin folder, then run php artisan config:clear.')
                ->assertFailed();
        } finally {
            putenv('PATH='.$originalPath);
        }

        $this->assertDatabaseHas('backup_monitoring', [
            'event' => 'manual_backup',
            'status' => 'failed',
            'message' => 'pg_dump was not found on PATH or the configured dump path. Set PG_DUMP_BINARY_PATH (preferred) or PGSQL_DUMP_PATH to the PostgreSQL bin folder, then run php artisan config:clear.',
        ]);

        $failedRuntime = DB::table('scheduled_task_logs')
            ->where('task', 'app:backup-run manual')
            ->where('status', 'failed')
            ->value('runtime_ms');

        $this->assertIsInt($failedRuntime);
    }

    public function test_manual_backup_route_redirects_with_error_when_backup_command_fails(): void
    {
        config([
            'backup.backup.source.databases' => ['pgsql'],
            'database.connections.pgsql.dump.dump_binary_path' => base_path('definitely-missing-pg-dump-folder-for-route-test'),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this
            ->actingAs($admin)
            ->from(route('admin.data.index'))
            ->post(route('admin.data.backups.run'))
            ->assertRedirect(route('admin.data.index', absolute: false))
            ->assertSessionHas('error');

        $this->assertStringContainsString(
            'Backup failed: pg_dump was not found',
            session('error')
        );
        $this->assertStringContainsString(
            'php artisan config:clear',
            session('error')
        );
    }

    public function test_manual_backup_route_redirects_with_error_when_artisan_call_throws(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('app:backup-run', ['scope' => 'manual'])
            ->andThrow(new RuntimeException('Scheduled task logging failed.'));

        $this
            ->actingAs($admin)
            ->from(route('admin.data.index'))
            ->post(route('admin.data.backups.run'))
            ->assertRedirect(route('admin.data.index', absolute: false))
            ->assertSessionHas('error', 'Backup failed: Scheduled task logging failed.');

        $this->assertDatabaseHas('backup_monitoring', [
            'initiated_by_user_id' => $admin->id,
            'event' => 'manual_backup',
            'status' => 'failed',
            'message' => 'Scheduled task logging failed.',
        ]);
    }

    public function test_backup_wrapper_refreshes_postgres_dump_config_from_environment_values(): void
    {
        config([
            'database.connections.pgsql.dump' => [
                'dump_binary_path' => base_path('old-postgres-bin'),
                'add_extra_option' => '--restrict-key=OldKey',
            ],
        ]);

        $method = new ReflectionMethod(AppBackupRun::class, 'applyPostgresDumpEnvironment');
        $method->setAccessible(true);
        $method->invoke(new AppBackupRun, [
            'PG_DUMP_BINARY_PATH' => 'C:\\Program Files\\PostgreSQL\\18\\bin',
            'PG_DUMP_RESTRICT_KEY' => 'PageTurnerBackup',
        ]);

        $this->assertSame('C:\\Program Files\\PostgreSQL\\18\\bin', config('database.connections.pgsql.dump.dump_binary_path'));
        $this->assertSame('--restrict-key=PageTurnerBackup', config('database.connections.pgsql.dump.add_extra_option'));
    }

    public function test_backup_preflight_accepts_configured_pg_dump_binary_path(): void
    {
        $directory = storage_path('framework/testing/postgres-bin');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        foreach (['pg_dump', 'pg_dump.exe'] as $binary) {
            $path = $directory.DIRECTORY_SEPARATOR.$binary;
            file_put_contents($path, '');
            chmod($path, 0755);
        }

        $method = new ReflectionMethod(AppBackupRun::class, 'pgDumpExists');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(new AppBackupRun, $directory));
    }

    public function test_backup_preflight_accepts_pg_dump_from_path(): void
    {
        $directory = storage_path('framework/testing/postgres-path-bin');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $binary = $directory.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'pg_dump.exe' : 'pg_dump');
        file_put_contents($binary, '');
        chmod($binary, 0755);

        $originalPath = getenv('PATH');
        putenv('PATH='.$directory.PATH_SEPARATOR.$originalPath);

        try {
            $method = new ReflectionMethod(AppBackupRun::class, 'pgDumpExists');
            $method->setAccessible(true);

            $this->assertTrue($method->invoke(new AppBackupRun, ''));
        } finally {
            putenv('PATH='.$originalPath);
        }
    }

    public function test_database_config_accepts_legacy_pgsql_dump_path_fallback(): void
    {
        $config = file_get_contents(config_path('database.php'));

        $this->assertStringContainsString("env('PG_DUMP_BINARY_PATH') ?: env('PGSQL_DUMP_PATH')", $config);
    }

    public function test_database_config_sets_postgres_restrict_key_dump_option(): void
    {
        $dumpConfig = config('database.connections.pgsql.dump');
        $example = file_get_contents(base_path('.env.example'));

        $this->assertSame('--restrict-key=PageTurnerBackup', $dumpConfig['add_extra_option']);
        $this->assertStringContainsString('PG_DUMP_RESTRICT_KEY=PageTurnerBackup', $example);
    }

    public function test_restrict_key_dump_failures_are_summarized_cleanly(): void
    {
        $method = new ReflectionMethod(AppBackupRun::class, 'conciseBackupFailureMessage');
        $method->setAccessible(true);

        $message = $method->invoke(
            new AppBackupRun,
            'Backup failed because: pg_dump: error: could not generate restrict key'
        );

        $this->assertSame(
            'pg_dump could not generate a restrict key. Set PG_DUMP_RESTRICT_KEY to an alphanumeric value such as PageTurnerBackup, then run php artisan config:clear.',
            $message
        );
    }

    public function test_backup_temp_file_failures_are_summarized_cleanly(): void
    {
        $method = new ReflectionMethod(AppBackupRun::class, 'conciseBackupFailureMessage');
        $method->setAccessible(true);

        $message = $method->invoke(
            new AppBackupRun,
            'TypeError fwrite(): Argument #1 ($stream) must be of type resource, bool given at vendor\spatie\db-dumper\src\Databases\PostgreSql.php:137'
        );

        $this->assertSame(
            'The backup process could not create a temporary PostgreSQL credentials file. Verify storage/app/backup-temp is writable, then try again.',
            $message
        );
    }

    public function test_blank_backup_archive_password_disables_encryption(): void
    {
        $this->assertNull(config('backup.backup.password'));
    }

    public function test_lab_six_schedule_contains_required_backup_and_maintenance_tasks(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString("Schedule::command('app:backup-run daily')", $schedule);
        $this->assertStringContainsString("Schedule::command('app:backup-run weekly')", $schedule);
        $this->assertStringContainsString("Schedule::command('backup:weekly-summary')", $schedule);
        $this->assertStringContainsString("Schedule::command('backup:clean')", $schedule);
        $this->assertStringContainsString("Schedule::command('order:cleanup-pending')", $schedule);
        $this->assertStringContainsString("Schedule::command('audit:archive')", $schedule);
        $this->assertStringContainsString('->withoutOverlapping()', $schedule);
        $this->assertStringContainsString('->onSuccess', $schedule);
        $this->assertStringContainsString('->onFailure', $schedule);
    }

    public function test_audit_logs_exclude_sensitive_fields_and_block_mutation(): void
    {
        $this->assertContains('password', config('audit.exclude'));
        $this->assertContains('remember_token', config('audit.exclude'));

        $user = User::factory()->create();
        app(AuditLogger::class)->userEvent($user, 'profile_reviewed', [], [
            'name' => $user->name,
        ]);

        $audit = Audit::where('event', 'profile_reviewed')->firstOrFail();
        $this->assertFalse($audit->delete());
        $this->assertDatabaseHas('audits', ['id' => $audit->id]);

        $audit->event = 'tampered';
        $this->assertFalse($audit->save());
    }

    public function test_two_factor_and_order_status_transition_create_custom_audits(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this
            ->actingAs($customer)
            ->post(route('twofactor.enable'))
            ->assertRedirect();

        $this->assertDatabaseHas('audits', [
            'event' => 'two_factor_enabled',
            'auditable_type' => User::class,
            'auditable_id' => $customer->id,
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'ORD-AUDIT-1',
            'receipt_number' => 'RCT-AUDIT-1',
            'buyer_name' => $customer->name,
            'buyer_email' => $customer->email,
            'address_line_1' => '123 Main St',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
            'country' => 'Philippines',
            'total_amount' => 500,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'placed_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.orders.update', $order), [
                'status' => 'completed',
                'payment_status' => 'paid',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audits', [
            'event' => 'order_status_transition',
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
        ]);
    }

    public function test_audit_checksum_verification_detects_tampering(): void
    {
        $user = User::factory()->create();
        app(AuditLogger::class)->userEvent($user, 'checksum_test', [], [
            'name' => 'Checksum Test',
        ]);

        $this->artisan('audit:verify-checksums')
            ->expectsOutput('All audit checksums are valid.')
            ->assertSuccessful();

        $audit = Audit::latest()->firstOrFail();
        DB::table('audits')->where('id', $audit->id)->update(['event' => 'tampered']);

        $this->artisan('audit:verify-checksums')
            ->expectsOutputToContain('Invalid audit checksums:')
            ->assertFailed();
    }

    public function test_standard_premium_admin_and_public_rate_limit_evidence(): void
    {
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $this->getJson('/api/v1/books')->assertOk();

        $standard = User::factory()->create(['role' => 'customer', 'subscription_tier' => 'standard']);
        $premium = User::factory()->create(['role' => 'customer', 'subscription_tier' => 'premium']);
        $admin = User::factory()->create(['role' => 'admin', 'subscription_tier' => 'standard']);

        $this->actingAs($standard)->getJson('/api/v1/orders')->assertOk();
        $this->actingAs($premium)->getJson('/api/v1/orders')->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/orders')->assertOk();

        foreach (['public', 'standard', 'premium', 'admin'] as $tier) {
            $this->assertDatabaseHas('api_rate_limit_hits', ['tier' => $tier]);
        }
    }

    public function test_rate_limit_429_response_contains_limit_information(): void
    {
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $this->getJson('/api/v1/books')->assertOk();
        $this->getJson('/api/v1/books')->assertOk();
        $this->getJson('/api/v1/books')->assertOk();

        $this->getJson('/api/v1/books')
            ->assertStatus(429)
            ->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining')
            ->assertJsonStructure(['message', 'tier', 'limit', 'retry_after']);
    }
}
