<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use App\Models\BackupMonitoring;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyBackupSummary extends Command
{
    use LogsScheduledTask;

    protected $signature = 'backup:weekly-summary {--dry-run : Build the summary without sending email}';

    protected $description = 'Email administrators a weekly backup summary.';

    public function handle(): int
    {
        return $this->logScheduledTask($this->signature, $this->description, function (): string {
            $events = BackupMonitoring::query()
                ->where('happened_at', '>=', now()->subWeek())
                ->latest('happened_at')
                ->get();

            $summary = "PageTurner weekly backup summary\n"
                .'Events: '.$events->count()."\n"
                .'Successful: '.$events->where('status', 'success')->count()."\n"
                .'Failed: '.$events->where('status', 'failed')->count()."\n"
                .'Latest status: '.($events->first()?->status ?? 'none');

            if (! $this->option('dry-run')) {
                User::where('role', 'admin')->pluck('email')->filter()->each(
                    fn (string $email) => Mail::raw($summary, fn ($message) => $message
                        ->to($email)
                        ->subject('PageTurner Weekly Backup Summary'))
                );
            }

            BackupMonitoring::create([
                'event' => 'weekly_summary',
                'status' => 'success',
                'message' => $this->option('dry-run') ? 'Weekly backup summary dry run generated.' : 'Weekly backup summary sent.',
                'metadata' => [
                    'events' => $events->count(),
                    'successes' => $events->where('status', 'success')->count(),
                    'failures' => $events->where('status', 'failed')->count(),
                    'dry_run' => (bool) $this->option('dry-run'),
                ],
                'happened_at' => now(),
            ]);

            return $this->option('dry-run') ? 'Weekly backup summary dry run generated.' : 'Weekly backup summary sent.';
        });
    }
}
