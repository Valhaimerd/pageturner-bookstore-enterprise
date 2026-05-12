<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Repositories\BookRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LabSevenCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_isbn_cache_is_invalidated_when_book_is_updated(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $book = Book::create([
            'category_id' => $category->id,
            'title' => 'Original Title',
            'slug' => 'original-title',
            'author' => 'Cache Author',
            'isbn' => '9783000000033',
            'price' => 700,
            'stock' => 15,
            'status' => 'active',
        ]);

        $repository = $this->app->make(BookRepository::class);

        $this->assertSame('Original Title', $repository->findActiveByIsbn($book->isbn)->title);

        $book->update(['title' => 'Updated Title']);

        $this->assertSame('Updated Title', $repository->findActiveByIsbn($book->isbn)->title);
    }

    public function test_second_catalog_request_uses_cached_result(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        Book::create([
            'category_id' => $category->id,
            'title' => 'Cached Catalog',
            'slug' => 'cached-catalog',
            'author' => 'Cache Author',
            'isbn' => '9783000001030',
            'price' => 700,
            'stock' => 15,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $repository = $this->app->make(BookRepository::class);

        DB::enableQueryLog();
        $repository->catalogCursor([], 12);
        $firstQueryCount = count(DB::getQueryLog());

        DB::flushQueryLog();
        $repository->catalogCursor([], 12);
        $secondQueryCount = count(DB::getQueryLog());

        $this->assertGreaterThan(0, $firstQueryCount);
        $this->assertSame(0, $secondQueryCount);
    }

    public function test_repeated_catalog_repository_calls_are_served_from_cache_without_redis(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        foreach (range(1, 10) as $index) {
            Book::create([
                'category_id' => $category->id,
                'title' => "Repeated Catalog {$index}",
                'slug' => "repeated-catalog-{$index}",
                'author' => 'Cache Author',
                'isbn' => '9783000005'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'price' => 700,
                'stock' => 15,
                'status' => 'active',
                'published_at' => now()->subMinutes($index),
            ]);
        }

        $repository = $this->app->make(BookRepository::class);
        $repository->catalogCursor([], 12);

        DB::enableQueryLog();

        for ($i = 0; $i < 50; $i++) {
            $this->assertCount(10, $repository->catalogCursor([], 12)->items());
        }

        $this->assertSame(0, count(DB::getQueryLog()));
    }

    public function test_non_tag_cache_store_tracks_and_invalidates_catalog_keys_without_flush(): void
    {
        if (in_array(config('cache.stores.'.config('cache.catalog_store', config('cache.default')).'.driver'), ['redis', 'memcached'], true)) {
            $this->markTestSkipped('Fallback key registry is only asserted for non-tag cache stores.');
        }

        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $book = Book::create([
            'category_id' => $category->id,
            'title' => 'Fallback Registry',
            'slug' => 'fallback-registry',
            'author' => 'Cache Author',
            'isbn' => '9783000006104',
            'price' => 700,
            'stock' => 15,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $repository = $this->app->make(BookRepository::class);
        $repository->catalogCursor([], 12);

        $store = Cache::store(config('cache.catalog_store', config('cache.default')));
        $this->assertNotEmpty($store->get('books:cache-registry:catalog'));

        $book->update(['title' => 'Fallback Registry Updated']);

        $this->assertNull($store->get('books:cache-registry:catalog'));
        $this->assertSame('Fallback Registry Updated', $repository->catalogCursor([], 12)->items()[0]->title);
    }

    public function test_category_catalog_cache_is_invalidated_when_book_changes(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $book = Book::create([
            'category_id' => $category->id,
            'title' => 'Old Category Title',
            'slug' => 'old-category-title',
            'author' => 'Cache Author',
            'isbn' => '9783000001047',
            'price' => 700,
            'stock' => 15,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $repository = $this->app->make(BookRepository::class);

        $this->assertSame('Old Category Title', $repository->categoryCursorCatalog('technology', 12)->items()[0]->title);

        $book->update(['title' => 'New Category Title']);

        $this->assertSame('New Category Title', $repository->categoryCursorCatalog('technology', 12)->items()[0]->title);
    }

    public function test_old_and_new_isbn_cache_entries_are_cleared_when_isbn_changes(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $book = Book::create([
            'category_id' => $category->id,
            'title' => 'Original ISBN',
            'slug' => 'original-isbn',
            'author' => 'Cache Author',
            'isbn' => '9783000001054',
            'price' => 700,
            'stock' => 15,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $repository = $this->app->make(BookRepository::class);

        $this->assertSame($book->id, $repository->findActiveByIsbn('9783000001054')->id);

        $book->update([
            'title' => 'Changed ISBN',
            'isbn' => '9783000001061',
        ]);

        $this->assertNull($repository->findActiveByIsbn('9783000001054'));
        $this->assertSame('Changed ISBN', $repository->findActiveByIsbn('9783000001061')->title);
    }

    public function test_book_invalidation_does_not_flush_unrelated_cache(): void
    {
        Cache::put('unrelated:cache-key', 'survives', 600);

        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $book = Book::create([
            'category_id' => $category->id,
            'title' => 'Unrelated Cache Guard',
            'slug' => 'unrelated-cache-guard',
            'author' => 'Cache Author',
            'isbn' => '9783000001078',
            'price' => 700,
            'stock' => 15,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $repository = $this->app->make(BookRepository::class);
        $repository->catalogCursor([], 12);

        $book->delete();

        $this->assertSame('survives', Cache::get('unrelated:cache-key'));
    }
}
