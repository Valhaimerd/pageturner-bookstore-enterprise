<?php

namespace App\Console\Commands\Concerns;

use App\Models\ScheduledTaskLog;

trait LogsScheduledTask
{
    protected function logScheduledTask(string $task, string $description, callable $callback): int
    {
        $startedAt = now();

        try {
            $message = $callback();

            ScheduledTaskLog::create([
                'task' => $task,
                'status' => 'success',
                'description' => $description,
                'output' => is_string($message) ? $message : null,
                'runtime_ms' => $startedAt->diffInMilliseconds(now()),
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);

            if (is_string($message) && $message !== '') {
                $this->info($message);
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            ScheduledTaskLog::create([
                'task' => $task,
                'status' => 'failed',
                'description' => $description,
                'output' => $exception->getMessage(),
                'runtime_ms' => $startedAt->diffInMilliseconds(now()),
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);

            $this->error($exception->getMessage());

            report($exception);

            return self::FAILURE;
        }
    }
}
