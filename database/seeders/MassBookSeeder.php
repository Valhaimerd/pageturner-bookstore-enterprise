<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MassBookSeeder extends Seeder
{
    public const DEFAULT_CHUNK_SIZE = 5000;

    public const DEFAULT_TOTAL_RECORDS = 1000000;

    protected const POSTGRES_MAX_BIND_PARAMETERS = 60000;

    protected const BOOK_INSERT_COLUMNS = 16;

    protected const POSTGRES_MEMORY_SAFE_CHUNK_SIZE = 1000;

    protected array $publishers = [
        'Apress',
        'Cengage Learning',
        'HarperCollins',
        'Manning Publications',
        'McGraw Hill',
        'No Starch Press',
        'O Reilly Media',
        'Packt Publishing',
        'Pearson Education',
        'Penguin Random House',
        'Pragmatic Bookshelf',
        'Springer',
        'Wiley',
        'World Scientific',
        'Que Publishing',
    ];

    protected array $formats = ['paperback', 'hardcover', 'ebook', 'audiobook'];

    protected array $topics = [
        'Laravel',
        'Databases',
        'Software Design',
        'Business Strategy',
        'World History',
        'Physics',
        'Study Skills',
        'Digital Marketing',
        'Data Science',
        'Creative Writing',
    ];

    protected array $titlePatterns = [
        'Practical %s Handbook %d',
        'Modern %s Essentials %d',
        'Complete Guide to %s %d',
        '%s for Professionals %d',
        'Applied %s Patterns %d',
    ];

    protected array $firstNames = [
        'Ada',
        'Nora',
        'Mara',
        'Ria',
        'Sean',
        'Mia',
        'Nico',
        'Kara',
        'Leo',
        'Iris',
        'Dante',
        'Lena',
    ];

    protected array $lastNames = [
        'Cruz',
        'Reyes',
        'Santos',
        'Garcia',
        'Mendoza',
        'Torres',
        'Lim',
        'Bautista',
        'Rivera',
        'Castillo',
        'Ramos',
        'Flores',
    ];

    protected ?int $totalRecords = null;

    protected ?int $chunkSize = null;

    protected ?int $isbnStart = null;

    public function configure(?int $totalRecords = null, ?int $chunkSize = null, ?int $isbnStart = null): self
    {
        $this->totalRecords = $totalRecords;
        $this->chunkSize = $chunkSize;
        $this->isbnStart = $isbnStart;

        return $this;
    }

    public function run(): void
    {
        $totalRecords = max(1, $this->totalRecords ?? (int) env('MASS_BOOK_SEED_TOTAL', self::DEFAULT_TOTAL_RECORDS));
        $requestedChunkSize = max(1, $this->chunkSize ?? (int) env('MASS_BOOK_SEED_CHUNK', self::DEFAULT_CHUNK_SIZE));
        $chunkSize = $this->effectiveChunkSize($requestedChunkSize);
        $isbnStart = max(0, $this->isbnStart ?? (int) env('MASS_BOOK_SEED_ISBN_START', 200000000));
        $inserted = 0;
        $startedAt = microtime(true);

        DB::disableQueryLog();

        $categoryIds = DB::table('categories')->pluck('id')->all();

        if ($categoryIds === []) {
            throw new \RuntimeException('MassBookSeeder requires at least one category record.');
        }

        $this->command?->info('Starting Lab 7 mass book seeding...');
        $this->command?->line('Target records: '.number_format($totalRecords));
        $this->command?->line('Chunk size: '.number_format($chunkSize));

        if ($chunkSize < $requestedChunkSize) {
            $this->command?->warn(
                'Requested chunk size reduced from '.number_format($requestedChunkSize)
                .' to '.number_format($chunkSize)
                .' for PostgreSQL prepared-statement parameter limits.'
            );
        }

        while ($inserted < $totalRecords) {
            $batchSize = min($chunkSize, $totalRecords - $inserted);
            $batch = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $sequence = $isbnStart + $inserted + $i;
                $batch[] = $this->bookRow($sequence, $categoryIds);
            }

            DB::table('books')->insert($batch);

            $inserted += $batchSize;
            unset($batch);
            gc_collect_cycles();

            if ($inserted % ($chunkSize * 10) === 0) {
                $this->command?->info(number_format($inserted).' books inserted...');
            }
        }

        $elapsedSeconds = microtime(true) - $startedAt;
        $peakMemoryMb = memory_get_peak_usage(true) / 1048576;
        $finalBookCount = DB::table('books')->count();

        $this->command?->info('Lab 7 mass book seeding complete.');
        $this->command?->line('Total inserted: '.number_format($inserted));
        $this->command?->line('Elapsed time: '.number_format($elapsedSeconds, 2).' seconds');
        $this->command?->line('Peak memory usage: '.number_format($peakMemoryMb, 2).' MB');
        $this->command?->line('Final book count: '.number_format($finalBookCount));
    }

    protected function effectiveChunkSize(int $requestedChunkSize): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $requestedChunkSize;
        }

        $postgresSafeChunk = intdiv(self::POSTGRES_MAX_BIND_PARAMETERS, self::BOOK_INSERT_COLUMNS);

        return max(1, min($requestedChunkSize, $postgresSafeChunk, self::POSTGRES_MEMORY_SAFE_CHUNK_SIZE));
    }

    protected function bookRow(int $sequence, array $categoryIds): array
    {
        $format = $this->formats[$sequence % count($this->formats)];
        $topic = $this->topics[$sequence % count($this->topics)];
        $title = sprintf($this->titlePatterns[$sequence % count($this->titlePatterns)], $topic, $sequence);
        $createdAt = now()->toDateTimeString();

        return [
            'category_id' => $categoryIds[$sequence % count($categoryIds)],
            'title' => $title,
            'slug' => Str::slug($title).'-'.$sequence,
            'author' => $this->firstNames[$sequence % count($this->firstNames)].' '.$this->lastNames[intdiv($sequence, 7) % count($this->lastNames)],
            'publisher' => $this->publishers[$sequence % count($this->publishers)],
            'format' => $format,
            'isbn' => $this->generateValidIsbn13($sequence),
            'description' => $this->description($topic, $format, $sequence),
            'price' => $this->priceForFormat($format, $sequence),
            'stock' => ($sequence * 37) % 1001,
            'cover_image' => null,
            'published_at' => now()->subDays($sequence % 12775)->toDateString(),
            'status' => $sequence % 20 === 0 ? 'inactive' : 'active',
            'is_featured' => $sequence % 13 === 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    protected function description(string $topic, string $format, int $sequence): string
    {
        return "A {$format} reference on {$topic} for PageTurner Lab 7 catalog benchmarking. Volume {$sequence} includes practical examples, review notes, and scalable bookstore metadata.";
    }

    protected function priceForFormat(string $format, int $sequence): float
    {
        [$min, $max] = match ($format) {
            'hardcover' => [850, 2499],
            'ebook' => [199, 899],
            'audiobook' => [299, 1199],
            default => [350, 1499],
        };

        return (float) ($min + (($sequence * 17) % (($max - $min) + 1)));
    }

    protected function generateValidIsbn13(int $sequence): string
    {
        $body = '978'.str_pad((string) ($sequence % 1000000000), 9, '0', STR_PAD_LEFT);
        $sum = 0;

        foreach (str_split($body) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - ($sum % 10)) % 10);
    }
}
