<?php

namespace Tests\Feature;

use App\Models\AiInteraction;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_receive_recommendations_from_active_books(): void
    {
        config(['ai.provider' => 'fake']);
        $book = $this->book('Laravel for Builders', 'laravel-for-builders', 'Technology', 'technology');

        $this->post(route('ai.assistant.store'), [
            'prompt' => 'I want a beginner friendly Laravel book.',
        ])
            ->assertOk()
            ->assertSee('Laravel for Builders')
            ->assertSee('Stock');

        $this->assertDatabaseHas('ai_interactions', [
            'provider' => 'fake',
            'status' => 'completed',
            'fallback_used' => false,
        ]);

        $interaction = AiInteraction::first();
        $this->assertSame([$book->id], array_intersect([$book->id], $interaction->recommended_book_ids));
    }

    public function test_inactive_books_are_never_recommended(): void
    {
        config(['ai.provider' => 'fake']);

        $this->book('Hidden Draft', 'hidden-draft', 'Technology', 'technology', 'inactive');
        $active = $this->book('Clean Code Essentials', 'clean-code-essentials', 'Technology', 'technology');

        $this->post(route('ai.assistant.store'), [
            'prompt' => 'Recommend technology books about code.',
        ])->assertOk()
            ->assertDontSee('Hidden Draft')
            ->assertSee('Clean Code Essentials');

        $this->assertSame([$active->id], AiInteraction::first()->recommended_book_ids);
    }

    public function test_primary_provider_failure_falls_back_and_logs_event(): void
    {
        config([
            'ai.provider' => 'ollama',
            'ai.fallback_provider' => 'fake',
            'ai.ollama.base_url' => 'http://ollama.test',
        ]);
        $this->book('Database Design Made Simple', 'database-design-made-simple', 'Technology', 'technology');

        Http::fake([
            'ollama.test/api/chat' => Http::response([], 500),
        ]);

        $this->post(route('ai.assistant.store'), [
            'prompt' => 'I need a database design guide.',
        ])
            ->assertOk()
            ->assertSee('Database Design Made Simple')
            ->assertSee('Offline fallback used');

        $this->assertDatabaseHas('ai_interactions', [
            'provider' => 'ollama',
            'fallback_provider' => 'fake',
            'status' => 'fallback',
            'fallback_used' => true,
            'estimated_cost_cents' => 0,
        ]);
    }

    public function test_api_endpoint_returns_camel_case_and_logs_rate_usage(): void
    {
        config(['ai.provider' => 'fake']);
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $this->book('API Design Handbook', 'api-design-handbook', 'Technology', 'technology');

        $this->postJson('/api/v1/ai/book-discovery', [
            'prompt' => 'Recommend a practical API book.',
        ])
            ->assertOk()
            ->assertHeader('ETag')
            ->assertJsonPath('data.recommendations.0.title', 'API Design Handbook')
            ->assertJsonStructure([
                'data' => [
                    'answer',
                    'intent',
                    'fallbackUsed',
                    'recommendations',
                    'interactionId',
                ],
            ])
            ->assertJsonMissingPath('data.fallback_used');

        $this->assertDatabaseHas('api_rate_limit_hits', [
            'tier' => 'public',
            'endpoint' => 'api.ai.book-discovery',
            'status_code' => 200,
        ]);
    }

    public function test_ai_endpoint_rate_limit_hits_are_logged_when_throttled(): void
    {
        config(['ai.provider' => 'fake']);
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');

        $this->book('Study Smarter', 'study-smarter', 'Education', 'education');

        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->postJson('/api/v1/ai/book-discovery', [
                'prompt' => 'Recommend a study book.',
            ]);
        }

        $responses[0]->assertOk();
        $responses[1]->assertOk();
        $responses[2]->assertStatus(429);

        $this->assertDatabaseHas('api_rate_limit_hits', [
            'endpoint' => 'api.ai.book-discovery',
            'status_code' => 429,
            'throttled' => true,
        ]);
    }

    protected function book(string $title, string $slug, string $categoryName, string $categorySlug, string $status = 'active'): Book
    {
        $category = Category::firstOrCreate(
            ['slug' => $categorySlug],
            ['name' => $categoryName, 'is_active' => true]
        );

        return Book::create([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'author' => 'Test Author',
            'publisher' => 'PageTurner Press',
            'format' => 'paperback',
            'isbn' => '9784'.str_pad((string) Book::count() + 1, 9, '0', STR_PAD_LEFT),
            'description' => 'A practical guide for Laravel, API, database, study, and code topics.',
            'price' => 799,
            'stock' => 10,
            'status' => $status,
            'published_at' => now(),
        ]);
    }
}
