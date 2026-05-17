<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

if (! function_exists('logScheduledCommand')) {
    function logScheduledCommand(string $task, string $status, string $description): void
    {
        \App\Models\ScheduledTaskLog::create([
            'task' => $task,
            'status' => $status,
            'description' => $description,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:backup-run daily')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('app:backup-run daily', 'success', 'Daily application backup'))
    ->onFailure(fn () => logScheduledCommand('app:backup-run daily', 'failed', 'Daily application backup'));

Schedule::command('app:backup-run weekly')
    ->weeklyOn(0, '02:30')
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('app:backup-run weekly', 'success', 'Weekly full application backup'))
    ->onFailure(fn () => logScheduledCommand('app:backup-run weekly', 'failed', 'Weekly full application backup'));

Schedule::command('backup:clean')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('backup:clean', 'success', 'Backup retention cleanup'))
    ->onFailure(fn () => logScheduledCommand('backup:clean', 'failed', 'Backup retention cleanup'));

Schedule::command('backup:monitor')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('backup:monitor', 'success', 'Backup health monitoring'))
    ->onFailure(fn () => logScheduledCommand('backup:monitor', 'failed', 'Backup health monitoring'));

Schedule::command('backup:weekly-summary')
    ->weeklyOn(0, '04:00')
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('backup:weekly-summary', 'success', 'Weekly backup summary email'))
    ->onFailure(fn () => logScheduledCommand('backup:weekly-summary', 'failed', 'Weekly backup summary email'));

Schedule::command('order:cleanup-pending')
    ->hourly()
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('order:cleanup-pending', 'success', 'Cancel pending orders older than 24 hours'))
    ->onFailure(fn () => logScheduledCommand('order:cleanup-pending', 'failed', 'Cancel pending orders older than 24 hours'));

Schedule::command('session:cleanup')
    ->daily()
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('session:cleanup', 'success', 'Clear expired sessions'))
    ->onFailure(fn () => logScheduledCommand('session:cleanup', 'failed', 'Clear expired sessions'));

Schedule::command('log:rotate')
    ->weekly()
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('log:rotate', 'success', 'Archive and compress old logs'))
    ->onFailure(fn () => logScheduledCommand('log:rotate', 'failed', 'Archive and compress old logs'));

Schedule::command('report:generate-daily')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('report:generate-daily', 'success', 'Generate daily sales report'))
    ->onFailure(fn () => logScheduledCommand('report:generate-daily', 'failed', 'Generate daily sales report'));

Schedule::command('notification:prune')
    ->weekly()
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('notification:prune', 'success', 'Delete old notification records'))
    ->onFailure(fn () => logScheduledCommand('notification:prune', 'failed', 'Delete old notification records'));

Schedule::command('audit:archive')
    ->monthly()
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('audit:archive', 'success', 'Archive audit logs older than one year'))
    ->onFailure(fn () => logScheduledCommand('audit:archive', 'failed', 'Archive audit logs older than one year'));

Schedule::command('system:health-check')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('system:health-check', 'success', 'Capture system health snapshot'))
    ->onFailure(fn () => logScheduledCommand('system:health-check', 'failed', 'Capture system health snapshot'));

Schedule::command('app:refresh-materialized-views')
    ->hourly()
    ->withoutOverlapping()
    ->onSuccess(fn () => logScheduledCommand('app:refresh-materialized-views', 'success', 'Refresh materialized reporting views'))
    ->onFailure(fn () => logScheduledCommand('app:refresh-materialized-views', 'failed', 'Refresh materialized reporting views'));
