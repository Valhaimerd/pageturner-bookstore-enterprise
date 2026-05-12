<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ActivitySixApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_book_api_transforms_fields_to_camel_case_and_filters_fields(): void
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        Book::create([
            'category_id' => $category->id,
            'title' => 'Domain Driven Design',
            'slug' => 'domain-driven-design',
            'author' => 'Eric Evans',
            'isbn' => '9789715087228',
            'price' => 1299,
            'stock' => 25,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/books?fields=title,created_at');

        $response
            ->assertOk()
            ->assertHeader('ETag')
            ->assertJsonPath('data.0.title', 'Domain Driven Design')
            ->assertJsonStructure([
                'data' => [
                    ['title', 'createdAt'],
                ],
            ])
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.author');
    }

    public function test_public_api_rate_limit_hits_are_logged_when_throttled(): void
    {
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $responses = [];

        for ($i = 0; $i < 4; $i++) {
            $responses[] = $this->getJson('/api/v1/books');
        }

        $responses[0]->assertOk();
        $responses[1]->assertOk();
        $responses[2]->assertOk();
        $responses[3]->assertStatus(429);

        $this->assertDatabaseHas('api_rate_limit_hits', [
            'tier' => 'public',
            'endpoint' => 'api.books.index',
            'status_code' => 429,
            'throttled' => true,
        ]);
    }

    public function test_authenticated_api_uses_customer_tier(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'subscription_tier' => 'premium',
        ]);

        $this
            ->actingAs($customer)
            ->getJson('/api/v1/orders')
            ->assertOk();

        $this->assertDatabaseHas('api_rate_limit_hits', [
            'user_id' => $customer->id,
            'tier' => 'premium',
            'endpoint' => 'api.orders.index',
            'status_code' => 200,
        ]);
    }
}
