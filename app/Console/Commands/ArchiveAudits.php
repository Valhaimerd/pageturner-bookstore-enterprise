<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use App\Models\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ArchiveAudits extends Command
{
    use LogsScheduledTask;

    protected $signature = 'audit:archive {--days=365}';

    protected $description = 'Archive audit records older than one year into append-only files.';

    public function handle(): int
    {
        return $this->logScheduledTask($this->signature, $this->description, function (): string {
            $archivePath = 'audit-archive/audits-'.now()->format('Ymd-His').'.jsonl';
            $count = 0;

            Audit::query()
                ->whereNull('archived_at')
                ->where('created_at', '<=', now()->subDays((int) $this->option('days')))
                ->orderBy('id')
                ->chunkById(200, function ($audits) use (&$count, $archivePath): void {
                    foreach ($audits as $audit) {
                        Storage::disk('local')->append($archivePath, json_encode([
                            'id' => $audit->id,
                            'event' => $audit->event,
                            'auditable_type' => $audit->auditable_type,
                            'auditable_id' => $audit->auditable_id,
                            'old_values' => $audit->old_values,
                            'new_values' => $audit->new_values,
                            'metadata' => $audit->metadata,
                            'checksum' => $audit->checksum,
                            'created_at' => optional($audit->created_at)->toIso8601String(),
                        ]));

                        $audit->update(['archived_at' => now()]);
                        $count++;
                    }
                });

            return "Archived {$count} audit log entries to {$archivePath}.";
        });
    }
}
