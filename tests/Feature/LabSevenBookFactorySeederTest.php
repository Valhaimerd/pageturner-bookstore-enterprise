<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use Database\Factories\BookFactory;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MassBookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabSevenBookFactorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_factory_uses_current_schema_and_valid_isbn13_values(): void
    {
        Category::create(['name' => 'Technology', 'slug' => 'technology']);
        BookFactory::resetSequence(200000000);
        BookFactory::clearCategoryCache();

        $book = Book::factory()->make();

        $this->assertNotNull($book->publisher);
        $this->assertContains($book->format, ['paperback', 'hardcover', 'ebook', 'audiobook']);
        $this->assertIsInt($book->stock);
        $this->assertContains($book->status, ['active', 'inactive']);
        $this->assertTrue($this->isValidIsbn13($book->isbn));
    }

    public function test_mass_book_seeder_uses_chunked_insert_overrides(): void
    {
        Category::create(['name' => 'Technology', 'slug' => 'technology']);

        app(MassBookSeeder::class)
            ->configure(totalRecords: 12, chunkSize: 5, isbnStart: 300000000)
            ->run();

        $this->assertSame(12, Book::count());
        $this->assertDatabaseHas('books', [
            'stock' => Book::firstOrFail()->stock,
        ]);
    }

    public function test_mass_seeded_books_have_valid_foreign_keys_isbns_and_varied_data(): void
    {
        $technology = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $fiction = Category::create(['name' => 'Fiction', 'slug' => 'fiction']);
        BookFactory::resetSequence(500000000);
        BookFactory::clearCategoryCache();

        app(MassBookSeeder::class)
            ->configure(totalRecords: 25, chunkSize: 7, isbnStart: 500000000)
            ->run();

        $books = Book::query()->get();

        $this->assertCount(25, $books);
        $this->assertSame([], $books->pluck('category_id')->diff([$technology->id, $fiction->id])->values()->all());

        foreach ($books as $book) {
            $this->assertTrue($this->isValidIsbn13($book->isbn), "Invalid ISBN generated: {$book->isbn}");
            $this->assertNotNull($book->publisher);
            $this->assertContains($book->format, ['paperback', 'hardcover', 'ebook', 'audiobook']);
            $this->assertContains($book->status, ['active', 'inactive']);
        }

        $this->assertGreaterThan(1, $books->pluck('title')->unique()->count());
        $this->assertGreaterThan(1, $books->pluck('author')->unique()->count());
        $this->assertGreaterThan(1, $books->pluck('publisher')->unique()->count());
        $this->assertGreaterThan(1, $books->pluck('format')->unique()->count());
        $this->assertGreaterThan(1, $books->pluck('price')->unique()->count());
        $this->assertGreaterThan(1, $books->pluck('stock')->unique()->count());
    }

    public function test_database_seeder_does_not_mass_seed_by_default(): void
    {
        putenv('MASS_BOOK_SEED_ENABLED=false');

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(15, Book::count());

        putenv('MASS_BOOK_SEED_ENABLED');
    }

    public function test_books_seed_mass_command_reports_verification_output(): void
    {
        Category::create(['name' => 'Technology', 'slug' => 'technology']);

        $this->artisan('books:seed-mass', [
            '--count' => 12,
            '--chunk' => 5,
            '--isbn-start' => 400000000,
        ])
            ->expectsOutput('Starting Lab 7 mass book seeding...')
            ->expectsOutput('Target records: 12')
            ->expectsOutput('Chunk size: 5')
            ->expectsOutput('Lab 7 mass book seeding complete.')
            ->expectsOutput('Total inserted: 12')
            ->expectsOutput('Final book count: 12')
            ->assertSuccessful();

        $this->assertSame(12, Book::count());
    }

    protected function isValidIsbn13(string $isbn): bool
    {
        if (! preg_match('/^\d{13}$/', $isbn)) {
            return false;
        }

        $sum = 0;

        foreach (str_split(substr($isbn, 0, 12)) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return ((10 - ($sum % 10)) % 10) === (int) substr($isbn, -1);
    }
}
