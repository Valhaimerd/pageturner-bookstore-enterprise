<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class IndexBooksBatch extends Command
{
    protected $signature = 'books:index-batch {--chunk=2000 : Number of books per indexing batch} {--sync : Index synchronously instead of queueing Scout jobs}';

    protected $description = 'Index active books for Scout search in observable chunks.';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $indexed = 0;

        Book::query()
            ->with('category:id,name')
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($books) use (&$indexed): void {
                $this->option('sync') ? $books->searchableSync() : $books->searchable();
                $indexed += $books->count();
                $this->info(number_format($indexed).' active books submitted for indexing...');
            });

        $this->info(number_format($indexed).' active books processed for Scout indexing.');

        return self::SUCCESS;
    }
}
