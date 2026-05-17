<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LogsScheduledTask;
use App\Models\Audit;
use Illuminate\Console\Command;

class VerifyAuditChecksums extends Command
{
    use LogsScheduledTask;

    protected $signature = 'audit:verify-checksums';

    protected $description = 'Verify audit log checksums and report tampered entries.';

    public function handle(): int
    {
        $tampered = [];

        $result = $this->logScheduledTask($this->signature, $this->description, function () use (&$tampered): string {
            Audit::query()
                ->orderBy('id')
                ->chunkById(200, function ($audits) use (&$tampered): void {
                    foreach ($audits as $audit) {
                        if (! $audit->hasValidChecksum()) {
                            $tampered[] = $audit->id;
                        }
                    }
                });

            if ($tampered !== []) {
                throw new \RuntimeException('Invalid audit checksums: '.implode(', ', $tampered));
            }

            return 'All audit checksums are valid.';
        });

        return $result;
    }
}
