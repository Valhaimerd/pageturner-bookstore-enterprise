<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use App\Models\BackupMonitoring;
use Dotenv\Parser\Parser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class AppBackupRun extends Command
{
    use LogsScheduledTask;

    private const PG_DUMP_MISSING_MESSAGE = 'pg_dump was not found on PATH or the configured dump path. Set PG_DUMP_BINARY_PATH (preferred) or PGSQL_DUMP_PATH to the PostgreSQL bin folder, then run php artisan config:clear.';

    private const PG_DUMP_RESTRICT_KEY_MESSAGE = 'pg_dump could not generate a restrict key. Set PG_DUMP_RESTRICT_KEY to an alphanumeric value such as PageTurnerBackup, then run php artisan config:clear.';

    protected $signature = 'app:backup-run {scope=daily : Backup scope label: daily, weekly, or manual} {--dry-run : Record evidence without running Spatie backup}';

    protected $description = 'Run an application backup and record Lab 6 monitoring evidence.';

    public function handle(): int
    {
        $scope = (string) $this->argument('scope');

        return $this->logScheduledTask('app:backup-run '.$scope, $this->description, function () use ($scope): string {
            $this->refreshPostgresDumpConfigFromEnvironmentFile();

            BackupMonitoring::create([
                'initiated_by_user_id' => auth()->id(),
                'event' => "{$scope}_backup",
                'status' => 'started',
                'message' => ucfirst($scope).' backup started.',
                'metadata' => ['scope' => $scope, 'dry_run' => (bool) $this->option('dry-run')],
                'happened_at' => now(),
            ]);

            if ($this->option('dry-run')) {
                BackupMonitoring::create([
                    'initiated_by_user_id' => auth()->id(),
                    'event' => "{$scope}_backup",
                    'status' => 'success',
                    'message' => ucfirst($scope).' backup dry run completed.',
                    'metadata' => ['scope' => $scope, 'dry_run' => true],
                    'happened_at' => now(),
                ]);

                return ucfirst($scope).' backup dry run completed.';
            }

            if ($message = $this->missingDumpToolMessage()) {
                BackupMonitoring::create([
                    'initiated_by_user_id' => auth()->id(),
                    'event' => "{$scope}_backup",
                    'status' => 'failed',
                    'message' => $message,
                    'metadata' => ['scope' => $scope, 'preflight' => 'database_dump_tool'],
                    'happened_at' => now(),
                ]);

                throw new \RuntimeException($message);
            }

            $backupProcess = $this->runBackupCommandInCliProcess();
            $exitCode = $backupProcess['exit_code'];
            $output = trim($backupProcess['output']);

            if ($exitCode !== self::SUCCESS) {
                $message = $this->conciseBackupFailureMessage($output);

                BackupMonitoring::create([
                    'initiated_by_user_id' => auth()->id(),
                    'event' => "{$scope}_backup",
                    'status' => 'failed',
                    'message' => $message,
                    'metadata' => [
                        'scope' => $scope,
                        'exit_code' => $exitCode,
                        'php_binary' => $backupProcess['php_binary'],
                        'raw_output' => str($output)->limit(2000)->toString(),
                        'temporary_directory' => $backupProcess['temporary_directory'],
                    ],
                    'happened_at' => now(),
                ]);

                throw new \RuntimeException('Backup command failed with exit code '.$exitCode.'.');
            }

            BackupMonitoring::create([
                'initiated_by_user_id' => auth()->id(),
                'event' => "{$scope}_backup",
                'status' => 'success',
                'message' => $output ?: ucfirst($scope).' backup completed.',
                'metadata' => ['scope' => $scope],
                'happened_at' => now(),
            ]);

            return ucfirst($scope).' backup completed.';
        });
    }

    protected function runBackupCommandInCliProcess(): array
    {
        $phpBinary = is_file(PHP_BINARY) ? PHP_BINARY : ((new PhpExecutableFinder)->find(false) ?: 'php');
        $temporaryDirectory = storage_path('app/backup-temp/php-temp');

        if (! is_dir($temporaryDirectory)) {
            mkdir($temporaryDirectory, 0775, true);
        }

        $process = $this->withTemporaryProcessEnvironment(
            $this->backupProcessEnvironment($temporaryDirectory, $phpBinary),
            function () use ($phpBinary, $temporaryDirectory): Process {
                $process = new Process(
                    [$phpBinary, '-d', 'sys_temp_dir='.$temporaryDirectory, 'artisan', 'backup:run', '--disable-notifications'],
                    base_path()
                );
                $process->setTimeout(null);
                $process->run();

                return $process;
            }
        );

        return [
            'exit_code' => $process->getExitCode() ?? self::FAILURE,
            'output' => trim($process->getOutput().PHP_EOL.$process->getErrorOutput()),
            'php_binary' => $phpBinary,
            'temporary_directory' => $temporaryDirectory,
        ];
    }

    protected function backupProcessEnvironment(string $temporaryDirectory, string $phpBinary): array
    {
        $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\\Windows';
        $postgresBinaryPath = (string) config('database.connections.pgsql.dump.dump_binary_path', '');
        $path = implode(PATH_SEPARATOR, array_filter([
            dirname($phpBinary),
            $postgresBinaryPath,
            getenv('PATH') ?: getenv('Path') ?: '',
            $systemRoot.'\\System32',
            $systemRoot,
            $systemRoot.'\\System32\\Wbem',
        ]));

        return [
            'ComSpec' => getenv('ComSpec') ?: $systemRoot.'\\System32\\cmd.exe',
            'Path' => $path,
            'PATH' => $path,
            'SystemRoot' => $systemRoot,
            'TEMP' => $temporaryDirectory,
            'TMP' => $temporaryDirectory,
            'TMPDIR' => $temporaryDirectory,
            'WINDIR' => $systemRoot,
        ];
    }

    protected function withTemporaryProcessEnvironment(array $environment, callable $callback): mixed
    {
        $original = [];

        foreach ($environment as $key => $value) {
            $original[$key] = getenv($key);
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        try {
            return $callback();
        } finally {
            foreach ($original as $key => $value) {
                if ($value === false) {
                    putenv($key);
                    unset($_ENV[$key], $_SERVER[$key]);

                    continue;
                }

                putenv($key.'='.$value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    protected function refreshPostgresDumpConfigFromEnvironmentFile(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $path = base_path('.env');

        if (! is_file($path)) {
            return;
        }

        $this->applyPostgresDumpEnvironment($this->environmentFileValues($path, [
            'PG_DUMP_BINARY_PATH',
            'PGSQL_DUMP_PATH',
            'PG_DUMP_RESTRICT_KEY',
        ]));
    }

    protected function applyPostgresDumpEnvironment(array $environment): void
    {
        $dumpBinaryPath = $environment['PG_DUMP_BINARY_PATH'] ?? '';

        if ($dumpBinaryPath === '') {
            $dumpBinaryPath = $environment['PGSQL_DUMP_PATH'] ?? '';
        }

        $restrictKey = $environment['PG_DUMP_RESTRICT_KEY'] ?? null;

        if ($dumpBinaryPath === '' && $restrictKey === null) {
            return;
        }

        foreach (config('database.connections', []) as $connectionName => $connection) {
            if (($connection['driver'] ?? null) !== 'pgsql') {
                continue;
            }

            $dump = $connection['dump'] ?? [];

            if ($dumpBinaryPath !== '') {
                $dump['dump_binary_path'] = $dumpBinaryPath;
            }

            if ($restrictKey !== null) {
                $dump['add_extra_option'] = '--restrict-key='.($restrictKey !== '' ? $restrictKey : 'PageTurnerBackup');
            }

            config(["database.connections.{$connectionName}.dump" => array_filter(
                $dump,
                fn ($value) => $value !== null && $value !== ''
            )]);
        }
    }

    protected function environmentFileValues(string $path, array $keys): array
    {
        try {
            $entries = (new Parser)->parse(file_get_contents($path));
        } catch (\Throwable) {
            return [];
        }

        $values = [];

        foreach ($entries as $entry) {
            if (! in_array($entry->getName(), $keys, true)) {
                continue;
            }

            $values[$entry->getName()] = $entry
                ->getValue()
                ->map(fn ($value) => $value->getChars())
                ->getOrElse('');
        }

        return $values;
    }

    protected function missingDumpToolMessage(): ?string
    {
        foreach ($this->postgresBackupConnections() as $connectionName => $connection) {
            $dumpBinaryPath = (string) data_get($connection, 'dump.dump_binary_path', '');

            if (! $this->pgDumpExists($dumpBinaryPath)) {
                return self::PG_DUMP_MISSING_MESSAGE;
            }
        }

        return null;
    }

    protected function conciseBackupFailureMessage(string $output): string
    {
        if (str_contains($output, 'pg_dump') && str_contains($output, 'not recognized')) {
            return self::PG_DUMP_MISSING_MESSAGE;
        }

        if (str_contains($output, 'could not generate restrict key')) {
            return self::PG_DUMP_RESTRICT_KEY_MESSAGE;
        }

        if (str_contains($output, 'pg_dump: error:')) {
            return 'pg_dump failed while dumping the PostgreSQL database. Run php artisan app:backup-run manual from the terminal for the full database dump error, and verify DB credentials, PG_DUMP_BINARY_PATH, and PG_DUMP_RESTRICT_KEY.';
        }

        if (str_contains($output, 'fwrite(): Argument #1 ($stream) must be of type resource, bool given')) {
            return 'The backup process could not create a temporary PostgreSQL credentials file. Verify storage/app/backup-temp is writable, then try again.';
        }

        return $output ?: 'Backup command failed.';
    }

    protected function postgresBackupConnections(): array
    {
        $connections = [];

        foreach (Arr::wrap(config('backup.backup.source.databases', [])) as $connectionName) {
            $connection = config("database.connections.{$connectionName}");

            if (is_array($connection) && ($connection['driver'] ?? null) === 'pgsql') {
                $connections[$connectionName] = $connection;
            }
        }

        return $connections;
    }

    protected function pgDumpExists(string $dumpBinaryPath = ''): bool
    {
        if ($dumpBinaryPath !== '') {
            return $this->pgDumpExistsInConfiguredPath($dumpBinaryPath);
        }

        return $this->binaryExists('pg_dump') || $this->binaryExists('pg_dump.exe');
    }

    protected function pgDumpExistsInConfiguredPath(string $dumpBinaryPath = ''): bool
    {
        return $dumpBinaryPath !== ''
            && (
                $this->binaryExists($dumpBinaryPath.DIRECTORY_SEPARATOR.'pg_dump')
                || $this->binaryExists($dumpBinaryPath.DIRECTORY_SEPARATOR.'pg_dump.exe')
            );
    }

    protected function binaryExists(string $binary): bool
    {
        if ($binary === '') {
            return false;
        }

        if (str_contains($binary, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $binary) === 1) {
            if (PHP_OS_FAMILY === 'Windows' && str_ends_with(strtolower($binary), '.exe')) {
                return is_file($binary);
            }

            return is_file($binary) && is_executable($binary);
        }

        return (new ExecutableFinder)->find($binary) !== null;
    }
}
