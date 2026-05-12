<?php

namespace App\Jobs;

use App\Models\ExportLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class CompleteExportLog implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        protected int $exportLogId,
        protected string $path,
        protected int $totalRows
    ) {}

    public function handle(): void
    {
        ExportLog::whereKey($this->exportLogId)->update([
            'status' => 'completed',
            'path' => $this->path,
            'total_rows' => $this->totalRows,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
