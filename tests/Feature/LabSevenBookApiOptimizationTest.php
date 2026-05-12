<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Repositories\BookRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LabSevenBookApiOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_book_api_uses_cursor_catalog_and_current_stock_status_fields(): void
    {
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        Book::create([
            'category_id' => $category->id,
            'title' => 'Scalable Laravel',
            'slug' => 'scalable-laravel',
            'author' => 'Ada Cruz',
            'publisher' => 'Apress',
            'format' => 'paperback',
            'isbn' => '9783000000002',
            'price' => 899,
            'stock' => 25,
            'status' => 'active',
            'published_at' => now(),
        ]);

        Book::create([
            'category_id' => $category->id,
            'title' => 'Hidden Draft',
            'slug' => 'hidden-draft',
            'author' => 'Ada Cruz',
            'isbn' => '9783000000019',
            'price' => 500,
            'stock' => 10,
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/v1/books?perPage=20');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'title', 'author', 'isbn', 'price', 'stock', 'status', 'category'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('data.0.title', 'Scalable Laravel')
            ->assertJsonPath('data.0.stock', 25)
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonMissing(['title' => 'Hidden Draft']);

        $this->assertSame(20, $response->json('meta.perPage'));
        $this->assertArrayHasKey('nextCursor', $response->json('meta'));
        $this->assertArrayHasKey('prevCursor', $response->json('meta'));
    }

    public function test_book_show_resource_includes_description_only_on_detail_route(): void
    {
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $book = Book::create([
            'category_id' => $category->id,
            'title' => 'Indexed Catalogs',
            'slug' => 'indexed-catalogs',
            'author' => 'Nora Reyes',
            'publisher' => 'Manning Publications',
            'format' => 'ebook',
            'isbn' => '9783000000026',
            'description' => 'A catalog performance guide.',
            'price' => 499,
            'stock' => 50,
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/books/{$book->slug}")
            ->assertOk()
            ->assertJsonPath('data.description', 'A catalog performance guide.')
            ->assertJsonPath('data.category.slug', 'technology');
    }

    public function test_web_catalog_uses_cursor_pagination_without_breaking_view(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        foreach (range(1, 13) as $index) {
            Book::create([
                'category_id' => $category->id,
                'title' => "Cursor Catalog {$index}",
                'slug' => "cursor-catalog-{$index}",
                'author' => 'Ada Cruz',
                'isbn' => '9783001'.str_pad((string) $index, 6, '0', STR_PAD_LEFT),
                'price' => 500,
                'stock' => 25,
                'status' => 'active',
                'published_at' => now()->subDays($index),
            ]);
        }

        $this->get('/books')
            ->assertOk()
            ->assertSee('Cursor Catalog 1')
            ->assertSee('Next');
    }

    public function test_api_category_filter_uses_slug_and_excludes_other_categories(): void
    {
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $technology = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $fiction = Category::create(['name' => 'Fiction', 'slug' => 'fiction']);

        Book::create([
            'category_id' => $technology->id,
            'title' => 'Indexed Laravel',
            'slug' => 'indexed-laravel',
            'author' => 'Ada Cruz',
            'isbn' => '9783000000040',
            'price' => 899,
            'stock' => 25,
            'status' => 'active',
            'published_at' => now(),
        ]);

        Book::create([
            'category_id' => $fiction->id,
            'title' => 'Fictional Query',
            'slug' => 'fictional-query',
            'author' => 'Mara Ellis',
            'isbn' => '9783000000057',
            'price' => 599,
            'stock' => 8,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/books?category=technology')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Indexed Laravel')
            ->assertJsonMissing(['title' => 'Fictional Query']);
    }

    public function test_repository_exact_isbn_lookup_returns_active_cached_book(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $book = Book::create([
            'category_id' => $category->id,
            'title' => 'ISBN Cache Hit',
            'slug' => 'isbn-cache-hit',
            'author' => 'Cache Author',
            'isbn' => '9783000000064',
            'price' => 799,
            'stock' => 12,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $repository = $this->app->make(BookRepository::class);

        $this->assertSame($book->id, $repository->findActiveByIsbn('9783000000064')->id);
        $this->assertSame($book->id, $repository->findActiveByIsbn('9783000000064')->id);
    }

    public function test_repository_exact_isbn_lookup_excludes_inactive_books(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        Book::create([
            'category_id' => $category->id,
            'title' => 'Inactive ISBN',
            'slug' => 'inactive-isbn',
            'author' => 'Cache Author',
            'isbn' => '9783000002122',
            'price' => 799,
            'stock' => 12,
            'status' => 'inactive',
            'published_at' => now(),
        ]);

        $repository = $this->app->make(BookRepository::class);

        $this->assertNull($repository->findActiveByIsbn('9783000002122'));
    }

    public function test_api_catalog_listing_avoids_obvious_n_plus_one_queries(): void
    {
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        foreach (range(1, 5) as $categoryIndex) {
            $category = Category::create([
                'name' => "Category {$categoryIndex}",
                'slug' => "category-{$categoryIndex}",
            ]);

            foreach (range(1, 2) as $bookIndex) {
                Book::create([
                    'category_id' => $category->id,
                    'title' => "N Plus One Guard {$categoryIndex}-{$bookIndex}",
                    'slug' => "n-plus-one-guard-{$categoryIndex}-{$bookIndex}",
                    'author' => 'Query Guard',
                    'isbn' => '9783002'.str_pad((string) (($categoryIndex * 10) + $bookIndex), 6, '0', STR_PAD_LEFT),
                    'price' => 799,
                    'stock' => 12,
                    'status' => 'active',
                    'published_at' => now()->subMinutes(($categoryIndex * 10) + $bookIndex),
                ]);
            }
        }

        DB::enableQueryLog();

        $this->getJson('/api/v1/books?perPage=10')->assertOk();

        $selectQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_starts_with(strtolower(trim($query['query'])), 'select'))
            ->count();

        $this->assertLessThanOrEqual(4, $selectQueries);
    }

    public function test_search_fallback_matches_title_author_isbn_and_description(): void
    {
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        Book::create([
            'category_id' => $category->id,
            'title' => 'Full Text Ready Catalog',
            'slug' => 'full-text-ready-catalog',
            'author' => 'Search Author',
            'isbn' => '9783000000071',
            'description' => 'Contains a rare optimization keyword.',
            'price' => 799,
            'stock' => 12,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/books?search=optimization')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Full Text Ready Catalog');

        $repository = $this->app->make(BookRepository::class);

        $this->assertSame('Full Text Ready Catalog', $repository->search('Full Text Ready')->items()[0]->title);
        $this->assertSame('Full Text Ready Catalog', $repository->search('Search Author')->items()[0]->title);
        $this->assertSame('Full Text Ready Catalog', $repository->search('9783000000071')->items()[0]->title);
    }

    public function test_search_can_be_combined_with_category_filter(): void
    {
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $technology = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $fiction = Category::create(['name' => 'Fiction', 'slug' => 'fiction']);

        Book::create([
            'category_id' => $technology->id,
            'title' => 'Category Scoped Search',
            'slug' => 'category-scoped-search',
            'author' => 'Shared Search Author',
            'isbn' => '9783000000088',
            'description' => 'Technology category match.',
            'price' => 799,
            'stock' => 12,
            'status' => 'active',
            'published_at' => now(),
        ]);

        Book::create([
            'category_id' => $fiction->id,
            'title' => 'Filtered Out Search',
            'slug' => 'filtered-out-search',
            'author' => 'Shared Search Author',
            'isbn' => '9783000000095',
            'description' => 'Fiction category match.',
            'price' => 699,
            'stock' => 10,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/books?search=Shared%20Search%20Author&category=technology')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Category Scoped Search')
            ->assertJsonMissing(['title' => 'Filtered Out Search']);
    }

    public function test_book_searchable_payload_and_active_indexing_rule_use_lab_seven_fields(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $book = Book::create([
            'category_id' => $category->id,
            'title' => 'Searchable Payload',
            'slug' => 'searchable-payload',
            'author' => 'Index Author',
            'publisher' => 'Index Press',
            'format' => 'hardcover',
            'isbn' => '9783000000101',
            'description' => 'Indexed payload description.',
            'price' => 1099,
            'stock' => 7,
            'status' => 'active',
            'published_at' => now(),
        ])->load('category');

        $inactiveBook = Book::create([
            'category_id' => $category->id,
            'title' => 'Inactive Payload',
            'slug' => 'inactive-payload',
            'author' => 'Index Author',
            'isbn' => '9783000000118',
            'price' => 999,
            'stock' => 0,
            'status' => 'inactive',
        ]);

        $this->assertTrue($book->shouldBeSearchable());
        $this->assertFalse($inactiveBook->shouldBeSearchable());
        $this->assertSame([
            'id' => $book->id,
            'title' => 'Searchable Payload',
            'author' => 'Index Author',
            'publisher' => 'Index Press',
            'format' => 'hardcover',
            'isbn' => '9783000000101',
            'description' => 'Indexed payload description.',
            'category' => 'Technology',
            'status' => 'active',
        ], $book->toSearchableArray());
    }

    public function test_chunked_indexing_command_processes_active_books_only(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        foreach ([1, 2] as $index) {
            Book::create([
                'category_id' => $category->id,
                'title' => "Index Batch {$index}",
                'slug' => "index-batch-{$index}",
                'author' => 'Batch Author',
                'isbn' => '9783000001'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'price' => 799,
                'stock' => 12,
                'status' => 'active',
                'published_at' => now(),
            ]);
        }

        Book::create([
            'category_id' => $category->id,
            'title' => 'Inactive Index Batch',
            'slug' => 'inactive-index-batch',
            'author' => 'Batch Author',
            'isbn' => '9783000001996',
            'price' => 799,
            'stock' => 12,
            'status' => 'inactive',
            'published_at' => now(),
        ]);

        $this->artisan('books:index-batch', ['--chunk' => 2, '--sync' => true])
            ->expectsOutput('2 active books submitted for indexing...')
            ->expectsOutput('2 active books processed for Scout indexing.')
            ->assertSuccessful();
    }
}
