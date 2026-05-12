<?php

namespace App\Console\Commands;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Database\Seeders\MassBookSeeder;
use Illuminate\Console\Command;

class SeedMassBooks extends Command
{
    private const MAX_CHUNK_SIZE = 10000;

    protected $signature = 'books:seed-mass
        {--count=1000000 : Number of book records to insert}
        {--chunk=5000 : Number of records per batch insert}
        {--isbn-start=200000000 : Numeric ISBN sequence start after the 978 prefix}';

    protected $description = 'Seed high-volume Lab 7 book records with chunked batch inserts.';

    public function handle(): int
    {
        $count = (int) $this->option('count');
        $chunk = (int) $this->option('chunk');
        $isbnStart = (int) $this->option('isbn-start');

        if ($count < 1) {
            $this->error('The --count option must be at least 1.');

            return self::FAILURE;
        }

        if ($chunk < 1) {
            $this->error('The --chunk option must be at least 1.');

            return self::FAILURE;
        }

        if ($chunk > self::MAX_CHUNK_SIZE) {
            $this->warn('Chunk size capped at '.number_format(self::MAX_CHUNK_SIZE).' to control memory usage.');
            $chunk = self::MAX_CHUNK_SIZE;
        }

        if ($isbnStart < 0) {
            $this->error('The --isbn-start option must be zero or greater.');

            return self::FAILURE;
        }

        if (! Category::query()->exists()) {
            $this->warn('No categories found. Running CategorySeeder first.');
            $this->call('db:seed', [
                '--class' => CategorySeeder::class,
                '--no-interaction' => true,
            ]);
        }

        /** @var \Database\Seeders\MassBookSeeder $seeder */
        $seeder = app(MassBookSeeder::class)
            ->configure($count, $chunk, $isbnStart)
            ->setCommand($this);

        $seeder->run();

        return self::SUCCESS;
    }
}
