<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_catalog_search_matches_description_keywords(): void
    {
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

        Book::create([
            'category_id' => $category->id,
            'title' => 'Unrelated Catalog Book',
            'slug' => 'unrelated-catalog-book',
            'author' => 'Another Author',
            'isbn' => '9783000000125',
            'description' => 'No matching topic.',
            'price' => 599,
            'stock' => 10,
            'status' => 'active',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/books?search=optimization')
            ->assertOk()
            ->assertSee('Full Text Ready Catalog')
            ->assertDontSee('Unrelated Catalog Book')
            ->assertSee('value="optimization"', false);
    }

    public function test_web_catalog_search_can_be_combined_with_category_filter(): void
    {
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

        $this->get('/books?search=Shared%20Search%20Author&category=technology')
            ->assertOk()
            ->assertSee('Category Scoped Search')
            ->assertDontSee('Filtered Out Search')
            ->assertSee('value="Shared Search Author"', false)
            ->assertSee('value="technology" selected', false);
    }

    public function test_web_catalog_search_no_match_shows_empty_state(): void
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        Book::create([
            'category_id' => $category->id,
            'title' => 'Visible Catalog Book',
            'slug' => 'visible-catalog-book',
            'author' => 'Catalog Author',
            'isbn' => '9783000000132',
            'description' => 'General topic.',
            'price' => 499,
            'stock' => 10,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->get('/books?search=does-not-exist')
            ->assertOk()
            ->assertSee('No books found')
            ->assertSee('Try another search term')
            ->assertDontSee('Visible Catalog Book');
    }
}
