<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onSuccess(fn () => \App\Models\ScheduledTaskLog::create([
        'task' => 'backup:run',
        'status' => 'success',
        'description' => 'Daily application backup',
        'started_at' => now(),
        'finished_at' => now(),
    ]))
    ->onFailure(fn () => \App\Models\ScheduledTaskLog::create([
        'task' => 'backup:run',
        'status' => 'failed',
        'description' => 'Daily application backup',
        'started_at' => now(),
        'finished_at' => now(),
    ]));

Schedule::command('backup:clean')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('backup:monitor')
    ->dailyAt('03:15')
    ->withoutOverlapping();

Schedule::command('order:cleanup-pending')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('session:cleanup')
    ->daily()
    ->withoutOverlapping();

Schedule::command('log:rotate')
    ->weekly()
    ->withoutOverlapping();

Schedule::command('report:generate-daily')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('notification:prune')
    ->weekly()
    ->withoutOverlapping();

Schedule::command('audit:archive')
    ->monthly()
    ->withoutOverlapping();

Schedule::command('system:health-check')
    ->dailyAt('06:30')
    ->withoutOverlapping();

Schedule::command('app:refresh-materialized-views')
    ->hourly()
    ->withoutOverlapping();
