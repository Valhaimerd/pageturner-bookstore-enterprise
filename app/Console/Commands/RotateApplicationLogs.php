<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RotateApplicationLogs extends Command
{
    use LogsScheduledTask;

    protected $signature = 'log:rotate {--days=7}';

    protected $description = 'Archive old Laravel log files using gzip compression.';

    public function handle(): int
    {
        return $this->logScheduledTask($this->signature, $this->description, function (): string {
            $deleted = 0;
            $threshold = now()->subDays((int) $this->option('days'))->getTimestamp();

            foreach (File::files(storage_path('logs')) as $file) {
                if (! str_ends_with($file->getFilename(), '.log') || $file->getMTime() >= $threshold) {
                    continue;
                }

                File::put($file->getRealPath().'.gz', gzencode(File::get($file->getRealPath()), 9));
                File::delete($file->getRealPath());
                $deleted++;
            }

            return "Compressed {$deleted} old log files.";
        });
    }
}
