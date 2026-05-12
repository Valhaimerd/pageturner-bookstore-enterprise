<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Category;
use App\Repositories\BookRepository;
use App\Services\AI\AIServiceManager;
use App\Services\AI\BookDiscoveryAIService;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\Providers\FakeAIProvider;
use App\Services\AI\Providers\OllamaProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookDiscoveryAIServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_are_grounded_in_active_books(): void
    {
        config(['ai.provider' => 'fake']);
        $active = $this->book('Laravel Grounded Guide', 'laravel-grounded-guide');
        $inactive = $this->book('Inactive Laravel Draft', 'inactive-laravel-draft', ['status' => 'inactive']);

        $result = app(BookDiscoveryAIService::class)->recommend('Recommend Laravel books.');

        $ids = collect($result['recommendations'])->pluck('book_id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertSame('fake', $result['provider_used']);
    }

    public function test_invalid_json_output_falls_back_to_local_recommendations(): void
    {
        $book = $this->book('Database Recovery Guide', 'database-recovery-guide');

        $manager = new class(app(OllamaProvider::class), app(FakeAIProvider::class)) extends AIServiceManager {
            public function __construct(OllamaProvider $ollamaProvider, FakeAIProvider $fakeProvider)
            {
                parent::__construct($ollamaProvider, $fakeProvider);
            }

            public function generateWithFallback(AIRequest $request): AIResponse
            {
                return new AIResponse(true, 'ollama', 'llama3.2', 'invalid json');
            }
        };

        $result = (new BookDiscoveryAIService(app(BookRepository::class), $manager))
            ->recommend('Recommend database books.');

        $this->assertSame('local', $result['provider_used']);
        $this->assertTrue($result['fallback_used']);
        $this->assertSame($book->id, $result['recommendations'][0]['book_id']);
    }

    public function test_invented_book_ids_are_removed(): void
    {
        config(['ai.provider' => 'ollama', 'ai.ollama.base_url' => 'http://ollama.test']);
        $book = $this->book('API Grounding Guide', 'api-grounding-guide');

        Http::fake([
            'ollama.test/api/chat' => Http::response([
                'message' => ['content' => json_encode([
                    'answer' => 'Use the real match.',
                    'recommendations' => [
                        ['book_id' => 9999, 'reason' => 'Invented', 'confidence' => 1],
                        ['book_id' => $book->id, 'reason' => 'Real', 'confidence' => 0.8],
                    ],
                    'confidence' => 0.8,
                    'needs_human_help' => false,
                ])],
            ], 200),
        ]);

        $result = app(BookDiscoveryAIService::class)->recommend('Recommend API books.');

        $this->assertSame([$book->id], collect($result['recommendations'])->pluck('book_id')->all());
    }

    public function test_low_confidence_and_html_output_are_handled_safely(): void
    {
        config(['ai.provider' => 'ollama', 'ai.ollama.base_url' => 'http://ollama.test']);
        $book = $this->book('Safe Output Guide', 'safe-output-guide');

        Http::fake([
            'ollama.test/api/chat' => Http::response([
                'message' => ['content' => json_encode([
                    'answer' => '<strong>Maybe this one</strong>',
                    'recommendations' => [
                        ['book_id' => $book->id, 'reason' => '<script>alert(1)</script>Safe match', 'confidence' => 0.2],
                    ],
                    'confidence' => 0.2,
                    'needs_human_help' => false,
                ])],
            ], 200),
        ]);

        $result = app(BookDiscoveryAIService::class)->recommend('Something vague.');

        $this->assertTrue($result['needs_human_help']);
        $this->assertStringNotContainsString('<', $result['answer']);
        $this->assertStringNotContainsString('<', $result['recommendations'][0]['reason']);
    }

    protected function book(string $title, string $slug, array $overrides = []): Book
    {
        $category = Category::firstOrCreate(
            ['slug' => 'technology'],
            ['name' => 'Technology', 'is_active' => true]
        );

        return Book::factory()->create(array_merge([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'author' => 'Test Author',
            'description' => 'A practical Laravel, database, API, programming, and testing guide.',
            'price' => 499,
            'stock' => 6,
            'status' => 'active',
        ], $overrides));
    }
}
