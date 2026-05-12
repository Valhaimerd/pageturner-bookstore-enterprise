<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class CleanupExpiredSessions extends Command
{
    use LogsScheduledTask;

    protected $signature = 'session:cleanup';

    protected $description = 'Remove expired session records or files.';

    public function handle(): int
    {
        return $this->logScheduledTask($this->signature, $this->description, function (): string {
            $lifetimeMinutes = (int) config('session.lifetime', 120);

            if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
                $deleted = DB::table('sessions')
                    ->where('last_activity', '<', now()->subMinutes($lifetimeMinutes)->timestamp)
                    ->delete();

                return "Deleted {$deleted} expired database sessions.";
            }

            $deleted = 0;

            foreach (File::files(storage_path('framework/sessions')) as $file) {
                if ($file->getMTime() < now()->subMinutes($lifetimeMinutes)->getTimestamp()) {
                    File::delete($file->getRealPath());
                    $deleted++;
                }
            }

            return "Deleted {$deleted} expired session files.";
        });
    }
}
