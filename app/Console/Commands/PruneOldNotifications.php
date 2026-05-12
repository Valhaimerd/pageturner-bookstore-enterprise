<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOldNotifications extends Command
{
    use LogsScheduledTask;

    protected $signature = 'notification:prune {--days=90}';

    protected $description = 'Delete notification records older than the configured retention window.';

    public function handle(): int
    {
        return $this->logScheduledTask($this->signature, $this->description, function (): string {
            $deleted = DB::table('notifications')
                ->where('created_at', '<=', now()->subDays((int) $this->option('days')))
                ->delete();

            return "Pruned {$deleted} notifications.";
        });
    }
}
