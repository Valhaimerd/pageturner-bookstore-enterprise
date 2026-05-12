<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use App\Models\BackupMonitoring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckSystemHealth extends Command
{
    use LogsScheduledTask;

    protected $signature = 'system:health-check';

    protected $description = 'Capture a quick system health snapshot for storage, queue, and backups.';

    public function handle(): int
    {
        return $this->logScheduledTask($this->signature, $this->description, function (): string {
            $snapshot = [
                'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'latest_backup_status' => BackupMonitoring::latest('happened_at')->value('status'),
            ];

            return 'System health snapshot: '.json_encode($snapshot);
        });
    }
}
