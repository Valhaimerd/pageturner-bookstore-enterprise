<?php

namespace Tests\Feature;

use App\Models\AIConversation;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Services\AI\BookDiscoveryAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

class BookAssistantBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.provider' => 'fake']);
        RateLimiter::clear('ip:127.0.0.1:second');
        RateLimiter::clear('ip:127.0.0.1:minute');
    }

    public function test_guest_web_user_can_send_message_and_creates_session_conversation(): void
    {
        $this->book('Laravel Starter Guide', 'laravel-starter-guide');

        $response = $this->withSession(['started' => true])
            ->post('/ai-assistant/messages', ['message' => 'Recommend beginner Laravel books.']);

        $response->assertOk();

        $conversation = AIConversation::first();
        $this->assertNotNull($conversation);
        $this->assertNull($conversation->user_id);
        $this->assertNotNull($conversation->session_id);
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertDatabaseHas('ai_messages', ['role' => 'user', 'content' => 'Recommend beginner Laravel books.']);
        $this->assertDatabaseHas('ai_messages', ['role' => 'assistant', 'provider' => 'fake']);
    }

    public function test_ai_assistant_ui_loads_with_notice_demo_prompts_and_loading_state(): void
    {
        $this->get('/ai-assistant')
            ->assertOk()
            ->assertSee('AI Book Assistant')
            ->assertSee('AI-generated suggestions. Please verify book details before buying.')
            ->assertSee('I want something inspiring about friendship')
            ->assertSee('Recommend beginner Laravel books')
            ->assertSee('Find books under 500 pesos about programming')
            ->assertSee('What should I read after learning PHP?')
            ->assertSee('Sending...')
            ->assertSee('Loading recommendations...');
    }

    public function test_guest_message_submission_displays_answer_recommendations_and_provider_badge(): void
    {
        $this->book('Laravel Starter Guide', 'laravel-starter-guide');

        $this->withSession(['started' => true])
            ->post('/ai-assistant/messages', ['message' => 'Recommend beginner Laravel books.'])
            ->assertOk()
            ->assertSee('AI-generated response')
            ->assertSee('Laravel Starter Guide')
            ->assertSee('Test Author')
            ->assertSee('Technology')
            ->assertSee('View Book')
            ->assertSee('Fake');
    }

    public function test_fallback_badge_displays_when_fake_fallback_is_used(): void
    {
        config([
            'ai.enabled' => false,
            'ai.provider' => 'ollama',
        ]);

        $this->book('PHP Web Builder', 'php-web-builder');

        $this->post('/ai-assistant/messages', ['message' => 'Recommend PHP books.'])
            ->assertOk()
            ->assertSee('Fake')
            ->assertSee('Fallback used');
    }

    public function test_web_validation_error_displays_for_empty_message(): void
    {
        $this->from('/ai-assistant')
            ->post('/ai-assistant/messages', ['message' => ''])
            ->assertRedirect('/ai-assistant');

        $this->followingRedirects()
            ->from('/ai-assistant')
            ->post('/ai-assistant/messages', ['message' => ''])
            ->assertOk()
            ->assertSee('The message field is required.');
    }

    public function test_safe_error_state_displays_when_ai_service_fails(): void
    {
        $this->mock(BookDiscoveryAIService::class, function ($mock): void {
            $mock->shouldReceive('recommend')->andThrow(new RuntimeException('raw ollama failure'));
        });

        $this->book('Clean Code Starter', 'clean-code-starter');

        $this->post('/ai-assistant/messages', ['message' => 'Recommend programming books.'])
            ->assertOk()
            ->assertSee('AI assistant could not complete the request safely.')
            ->assertSee('AI service is temporarily unavailable. Please try again later.')
            ->assertDontSee('raw ollama failure');
    }

    public function test_catalog_and_navigation_link_to_ai_book_assistant(): void
    {
        $this->book('Clean Code Starter', 'clean-code-starter');

        $this->get('/books')
            ->assertOk()
            ->assertSee('Ask AI for book recommendations')
            ->assertSee(route('ai.book-assistant.index'), false)
            ->assertSee('AI Book Assistant');
    }

    public function test_authenticated_user_can_send_message_and_creates_user_conversation(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->book('PHP Web Builder', 'php-web-builder');

        $response = $this->actingAs($user)
            ->post('/ai-assistant/messages', ['message' => 'Suggest a PHP programming book.']);

        $response->assertOk();

        $conversation = AIConversation::first();
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertNull($conversation->session_id);
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_api_guest_can_send_message_and_use_session_header_for_conversations(): void
    {
        $this->book('API Design Basics', 'api-design-basics');

        $response = $this->postJson('/api/ai/book-assistant/message', [
            'message' => 'Recommend API books.',
        ]);

        $response->assertOk()
            ->assertHeader('X-AI-Session-ID')
            ->assertJsonPath('providerUsed', 'fake')
            ->assertJsonPath('fallbackUsed', false)
            ->assertJsonCount(1, 'recommendations');

        $sessionId = $response->headers->get('X-AI-Session-ID');

        $this->getJson('/api/ai/book-assistant/conversations', [
            'X-AI-Session-ID' => $sessionId,
        ])->assertOk()
            ->assertHeader('X-AI-Session-ID', $sessionId)
            ->assertJsonCount(1, 'data');

        $conversationId = AIConversation::first()->id;
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.2'])
            ->getJson("/api/ai/book-assistant/conversations/{$conversationId}", [
            'X-AI-Session-ID' => $sessionId,
        ])->assertOk()
            ->assertJsonPath('data.id', $conversationId)
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_authenticated_api_user_cannot_access_another_users_conversation(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $conversation = AIConversation::factory()->for($owner, 'user')->create();

        $this->actingAs($other)
            ->getJson("/api/ai/book-assistant/conversations/{$conversation->id}")
            ->assertForbidden();
    }

    public function test_guest_cannot_access_another_session_conversation(): void
    {
        $conversation = AIConversation::factory()->create([
            'user_id' => null,
            'session_id' => 'api_other_session',
        ]);

        $this->getJson("/api/ai/book-assistant/conversations/{$conversation->id}", [
            'X-AI-Session-ID' => 'api_current_session',
        ])->assertForbidden();
    }

    public function test_invalid_message_returns_validation_error(): void
    {
        $this->postJson('/api/ai/book-assistant/message', [
            'message' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_ai_message_endpoint_is_rate_limited(): void
    {
        $this->book('Clean Code Starter', 'clean-code-starter');

        $this->postJson('/api/ai/book-assistant/message', ['message' => 'Recommend programming books.'])->assertOk();
        $this->postJson('/api/ai/book-assistant/message', ['message' => 'Recommend programming books.'])->assertOk();
        $this->postJson('/api/ai/book-assistant/message', ['message' => 'Recommend programming books.'])->assertStatus(429);
    }

    protected function book(string $title, string $slug): Book
    {
        $category = Category::firstOrCreate(
            ['slug' => 'technology'],
            ['name' => 'Technology', 'is_active' => true]
        );

        return Book::create([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'author' => 'Test Author',
            'publisher' => 'PageTurner Press',
            'format' => 'paperback',
            'isbn' => '9787'.str_pad((string) Book::count() + 1, 9, '0', STR_PAD_LEFT),
            'description' => 'A practical programming, Laravel, PHP, and API guide.',
            'price' => 499,
            'stock' => 8,
            'status' => 'active',
            'published_at' => now(),
        ]);
    }
}
