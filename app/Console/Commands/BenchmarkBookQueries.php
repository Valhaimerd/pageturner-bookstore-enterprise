<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Repositories\BookRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class BenchmarkBookQueries extends Command
{
    protected $signature = 'benchmark:books {--iterations=100 : Number of timed iterations per query}';

    protected $description = 'Benchmark Lab 7 catalog queries and store timing results.';

    protected int $warmupRuns = 2;

    protected array $targets = [
        'catalog_listing' => 100.0,
        'isbn_lookup' => 50.0,
        'category_filter' => 150.0,
        'full_text_search' => 300.0,
        'export_10k_chunk_simulation' => 30000.0,
    ];

    public function handle(BookRepository $books): int
    {
        $iterations = max(1, (int) $this->option('iterations'));
        $sampleBook = Book::query()->where('status', 'active')->whereNotNull('isbn')->first();
        $sampleCategoryId = Book::query()->where('status', 'active')->value('category_id');
        $searchTerm = $sampleBook?->title ? strtok($sampleBook->title, ' ') : 'Laravel';

        if (! $sampleBook || ! $sampleCategoryId) {
            $this->error('Benchmark requires at least one active book with an ISBN.');

            return self::FAILURE;
        }

        $benchmarks = [
            'catalog_listing' => fn () => $books->benchmarkCatalog(100),
            'isbn_lookup' => fn () => $books->findActiveByIsbn($sampleBook->isbn),
            'category_filter' => fn () => $books->benchmarkCategory($sampleCategoryId, 100),
            'full_text_search' => fn () => $books->search((string) $searchTerm, 100),
            'export_10k_chunk_simulation' => fn () => $this->simulateExportChunkQuery(10000),
        ];

        $failed = false;
        $rows = collect($benchmarks)->map(function (callable $callback, string $name) use ($iterations, &$failed) {
            $result = $this->measure($callback, $iterations);
            $target = $this->targets[$name];
            $passed = $result['average_ms'] <= $target;
            $failed = $failed || ! $passed;

            $this->record($name, $iterations, $result, $target, $passed);

            return [
                $name,
                number_format($result['average_ms'], 3),
                number_format($result['min_ms'], 3),
                number_format($result['max_ms'], 3),
                number_format($result['total_ms'], 3),
                number_format($target, 3),
                $passed ? 'yes' : 'no',
            ];
        })->values()->all();

        $this->table(['Benchmark', 'Avg ms', 'Min ms', 'Max ms', 'Total ms', 'Target ms', 'Pass'], $rows);
        $this->writeBenchmarkLog($rows, $iterations);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function measure(callable $callback, int $iterations): array
    {
        $timings = [];

        for ($i = 0; $i < $this->warmupRuns; $i++) {
            $callback();
        }

        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $callback();
            $timings[] = (hrtime(true) - $start) / 1000000;
        }

        return [
            'average_ms' => array_sum($timings) / count($timings),
            'min_ms' => min($timings),
            'max_ms' => max($timings),
            'total_ms' => array_sum($timings),
        ];
    }

    protected function record(string $name, int $iterations, array $result, float $target, bool $passed): void
    {
        if (! Schema::hasTable('query_performance_logs')) {
            return;
        }

        DB::table('query_performance_logs')->insert([
            'benchmark' => $name,
            'iterations' => $iterations,
            'average_ms' => $result['average_ms'],
            'min_ms' => $result['min_ms'],
            'max_ms' => $result['max_ms'],
            'total_ms' => $result['total_ms'],
            'target_ms' => $target,
            'passed' => $passed,
            'metadata' => json_encode([
                'driver' => DB::getDriverName(),
                'book_count' => Book::count(),
            ]),
            'created_at' => now(),
        ]);
    }

    protected function simulateExportChunkQuery(int $limit): int
    {
        $processed = 0;

        Book::query()
            ->select(['id', 'category_id', 'isbn', 'title', 'author', 'price', 'stock', 'status', 'published_at', 'created_at'])
            ->with('category:id,name')
            ->orderBy('id')
            ->chunkById(2000, function ($books) use (&$processed, $limit): bool {
                foreach ($books as $book) {
                    $book->category?->name;
                    $processed++;

                    if ($processed >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        return $processed;
    }

    protected function writeBenchmarkLog(array $rows, int $iterations): void
    {
        $path = storage_path('logs/lab7-benchmark.log');
        File::ensureDirectoryExists(dirname($path));

        File::append($path, implode(PHP_EOL, [
            '=== Lab 7 Book Benchmark ===',
            'Timestamp: '.now()->toDateTimeString(),
            'Environment: '.app()->environment(),
            'Laravel: '.app()->version(),
            'PHP: '.PHP_VERSION,
            'Database driver: '.DB::getDriverName(),
            'Book count: '.Book::count(),
            'Iterations: '.$iterations,
            'Warmup runs: '.$this->warmupRuns,
            'Hardware/spec note: fill CPU, RAM, disk, DB host, Redis status, and record count in README/evidence.',
            '',
            implode("\t", ['Benchmark', 'Avg ms', 'Min ms', 'Max ms', 'Total ms', 'Target ms', 'Pass']),
            ...array_map(fn (array $row) => implode("\t", $row), $rows),
            '',
        ]));
    }
}
