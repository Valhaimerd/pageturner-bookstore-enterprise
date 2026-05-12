<?php

namespace App\Imports\Concerns;

use App\Models\ImportLog;
use Illuminate\Support\Facades\Storage;

trait TracksImportProgress
{
    protected int $importLogId;

    protected function importLog(): ImportLog
    {
        return ImportLog::query()->findOrFail($this->importLogId);
    }

    protected function markProcessing(): void
    {
        ImportLog::whereKey($this->importLogId)->update([
            'status' => 'processing',
        ]);
    }

    protected function markCompleted(): void
    {
        ImportLog::whereKey($this->importLogId)->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    protected function markFailed(string $message): void
    {
        ImportLog::whereKey($this->importLogId)->update([
            'status' => 'failed',
            'completed_at' => now(),
            'metadata' => array_merge($this->importLog()->metadata ?? [], [
                'error' => $message,
            ]),
        ]);
    }

    protected function markRowSucceeded(): void
    {
        ImportLog::whereKey($this->importLogId)->incrementEach([
            'processed_rows' => 1,
            'success_rows' => 1,
        ]);
    }

    protected function markRowFailed(array $payload): void
    {
        $failurePath = $this->failureReportPath();

        Storage::disk('local')->append($failurePath, json_encode($payload));

        ImportLog::whereKey($this->importLogId)->incrementEach([
            'processed_rows' => 1,
            'failed_rows' => 1,
        ]);

        ImportLog::whereKey($this->importLogId)->update([
            'failure_report_path' => $failurePath,
            'status' => 'processing',
        ]);
    }

    protected function failureReportPath(): string
    {
        return "imports/failures/import-{$this->importLogId}.jsonl";
    }
}
