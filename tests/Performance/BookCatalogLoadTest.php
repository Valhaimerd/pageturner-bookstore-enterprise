<?php

namespace Tests\Performance;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class BookCatalogLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('LAB7_PERFORMANCE_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set LAB7_PERFORMANCE_TESTS=true to run Lab 7 load thresholds.');
        }
    }

    public function test_catalog_handles_repeated_requests_under_threshold(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        for ($i = 1; $i <= 100; $i++) {
            Book::create([
                'category_id' => $category->id,
                'title' => "Load Test Book {$i}",
                'slug' => "load-test-book-{$i}",
                'author' => 'Load Tester',
                'isbn' => '9783'.str_pad((string) $i, 9, '0', STR_PAD_LEFT),
                'price' => 500,
                'stock' => 20,
                'status' => 'active',
                'published_at' => now()->subDays($i),
            ]);
        }

        $totalMs = 0;

        for ($i = 0; $i < 50; $i++) {
            RateLimiter::clear('ip:127.0.0.1:second');
            RateLimiter::clear('ip:127.0.0.1:minute');

            $start = hrtime(true);
            $this->getJson('/api/v1/books?perPage=50')->assertOk();
            $totalMs += (hrtime(true) - $start) / 1000000;
        }

        $this->assertLessThan(150, $totalMs / 50);
    }
}
