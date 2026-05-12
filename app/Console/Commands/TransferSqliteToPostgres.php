<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransferSqliteToPostgres extends Command
{
    protected $signature = 'db:transfer-sqlite-to-pgsql
        {--source= : Absolute path to the source SQLite database file}
        {--target=pgsql : Target PostgreSQL connection name}
        {--chunk=1000 : Number of rows to transfer per insert batch}
        {--truncate : Truncate target tables before transfer}
        {--force : Run destructive target truncation without confirmation}';

    protected $description = 'Transfer existing PageTurner SQLite data into a migrated PostgreSQL database.';

    protected array $tables = [
        'users',
        'password_reset_tokens',
        'notifications',
        'categories',
        'books',
        'carts',
        'cart_items',
        'orders',
        'order_items',
        'reviews',
        'two_factor_challenges',
        'two_factor_secrets',
        'audits',
        'import_logs',
        'export_logs',
        'scheduled_task_logs',
        'api_rate_limit_hits',
        'backup_monitoring',
        'query_performance_logs',
        'mv_bestseller_stats',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    protected array $booleanColumns = [
        'users' => ['is_active', 'two_factor_enabled'],
        'categories' => ['is_active'],
        'books' => ['is_featured'],
        'reviews' => ['is_visible'],
        'api_rate_limit_hits' => ['throttled'],
        'query_performance_logs' => ['passed'],
    ];

    protected array $integerColumns = [
        'scheduled_task_logs' => ['runtime_ms'],
    ];

    public function handle(): int
    {
        $source = 'sqlite_transfer';
        $target = (string) $this->option('target');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $sourcePath = $this->sourcePath();

        if (! is_file($sourcePath)) {
            $this->error("SQLite source database does not exist: {$sourcePath}");

            return self::FAILURE;
        }

        config(['database.connections.'.$source.'.database' => $sourcePath]);
        DB::purge($source);
        DB::purge($target);

        if (! $this->validateConnections($source, $target)) {
            return self::FAILURE;
        }

        if (! Schema::connection($target)->hasTable('migrations')) {
            $this->error("Target connection [{$target}] has no migrations table. Run migrations on PostgreSQL first.");
            $this->line('Example: php artisan migrate --database=pgsql');

            return self::FAILURE;
        }

        $tables = $this->existingTables($source, $target);

        if ($tables === []) {
            $this->warn('No shared application tables were found to transfer.');

            return self::SUCCESS;
        }

        if ($this->option('truncate')) {
            if (! $this->option('force') && ! $this->confirm("This will truncate ".count($tables)." target PostgreSQL tables. Continue?")) {
                return self::FAILURE;
            }

            $this->truncateTargetTables($target, $tables);
        } elseif ($this->targetHasRows($target, $tables)) {
            $this->error('Target PostgreSQL tables already contain data. Re-run with --truncate after backing up the target.');

            return self::FAILURE;
        }

        $this->info("Transferring data from SQLite [{$sourcePath}] to PostgreSQL connection [{$target}]...");

        $totalRows = 0;
        $startedAt = microtime(true);

        foreach ($tables as $table) {
            $rows = $this->copyTable($source, $target, $table, $chunkSize);
            $totalRows += $rows;
            $this->line(str_pad($table, 32).number_format($rows).' rows');
        }

        $this->resetPostgresSequences($target, $tables);

        $elapsed = microtime(true) - $startedAt;
        $this->newLine();
        $this->info('SQLite to PostgreSQL transfer complete.');
        $this->info('Transferred rows: '.number_format($totalRows));
        $this->info('Elapsed time: '.number_format($elapsed, 2).' seconds');

        return self::SUCCESS;
    }

    protected function sourcePath(): string
    {
        $source = $this->option('source') ?: config('database.connections.sqlite_transfer.database');

        return $this->isAbsolutePath((string) $source)
            ? (string) $source
            : base_path((string) $source);
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    protected function validateConnections(string $source, string $target): bool
    {
        try {
            DB::connection($source)->getPdo();
            DB::connection($target)->getPdo();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return false;
        }

        if (DB::connection($source)->getDriverName() !== 'sqlite') {
            $this->error("Source connection [{$source}] must use SQLite.");

            return false;
        }

        if (DB::connection($target)->getDriverName() !== 'pgsql') {
            $this->error("Target connection [{$target}] must use PostgreSQL.");

            return false;
        }

        return true;
    }

    protected function existingTables(string $source, string $target): array
    {
        return array_values(array_filter($this->tables, fn (string $table) => Schema::connection($source)->hasTable($table)
            && Schema::connection($target)->hasTable($table)));
    }

    protected function targetHasRows(string $target, array $tables): bool
    {
        foreach ($tables as $table) {
            if (DB::connection($target)->table($table)->limit(1)->exists()) {
                return true;
            }
        }

        return false;
    }

    protected function truncateTargetTables(string $target, array $tables): void
    {
        $quotedTables = collect($tables)
            ->reverse()
            ->map(fn (string $table) => $this->quoteIdentifier($table))
            ->implode(', ');

        DB::connection($target)->statement("TRUNCATE TABLE {$quotedTables} RESTART IDENTITY CASCADE");
    }

    protected function copyTable(string $source, string $target, string $table, int $chunkSize): int
    {
        $columns = Schema::connection($source)->getColumnListing($table);
        $orderColumn = in_array('id', $columns, true) ? 'id' : $columns[0];
        $rowsCopied = 0;

        DB::connection($source)
            ->table($table)
            ->orderBy($orderColumn)
            ->chunk($chunkSize, function ($rows) use ($target, $table, &$rowsCopied): void {
                $batch = $rows
                    ->map(fn (object $row) => $this->normalizeRow($table, (array) $row))
                    ->all();

                if ($batch !== []) {
                    DB::connection($target)->table($table)->insert($batch);
                    $rowsCopied += count($batch);
                }

                unset($batch);
                gc_collect_cycles();
            });

        return $rowsCopied;
    }

    protected function normalizeRow(string $table, array $row): array
    {
        foreach ($this->booleanColumns[$table] ?? [] as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = (bool) $row[$column];
            }
        }

        foreach ($this->integerColumns[$table] ?? [] as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null && is_numeric($row[$column])) {
                $row[$column] = (int) round((float) $row[$column]);
            }
        }

        return $row;
    }

    protected function resetPostgresSequences(string $target, array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::connection($target)->hasColumn($table, 'id')) {
                continue;
            }

            $sequence = DB::connection($target)
                ->selectOne("SELECT pg_get_serial_sequence(?, 'id') AS sequence", [$table])
                ?->sequence;

            if (! $sequence) {
                continue;
            }

            $quotedTable = $this->quoteIdentifier($table);
            DB::connection($target)->statement(
                "SELECT setval(?, COALESCE((SELECT MAX(id) FROM {$quotedTable}), 1), (SELECT COUNT(*) FROM {$quotedTable}) > 0)",
                [$sequence]
            );
        }
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
