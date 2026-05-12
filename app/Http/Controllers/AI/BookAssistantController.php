<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\BookAssistantRequest;
use App\Http\Resources\AI\AIConversationResource;
use App\Http\Resources\AI\AIMessageResource;
use App\Jobs\ProcessAIConversationSummary;
use App\Models\AIConversation;
use App\Models\AIMessage;
use App\Services\AI\BookDiscoveryAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class BookAssistantController extends Controller
{
    public function __construct(protected BookDiscoveryAIService $bookDiscovery) {}

    public function index(Request $request)
    {
        $conversation = $this->latestConversation($request, $this->webSessionId($request));

        return view('ai.book-assistant', [
            'result' => null,
            'prompt' => '',
            'conversation' => $conversation?->load('messages'),
            'messages' => $conversation?->messages ?? collect(),
            'formAction' => route('ai.book-assistant.messages'),
            'inputName' => 'message',
        ]);
    }

    public function store(BookAssistantRequest $request)
    {
        $message = $request->validated('message');
        $sessionId = $this->webSessionId($request);
        $conversation = $this->resolveConversation($request, $sessionId);
        $userMessage = $this->storeUserMessage($conversation, $request, $message);
        $result = $this->safeRecommendation($message, $request, $conversation);
        $assistantMessage = $this->storeAssistantMessage($conversation, $result);
        $this->dispatchSummaryIfReady($conversation);

        return view('ai.book-assistant', [
            'result' => $this->viewResult($result),
            'prompt' => $message,
            'conversation' => $conversation->fresh('messages'),
            'messages' => $conversation->fresh('messages')->messages,
            'userMessage' => $userMessage,
            'assistantMessage' => $assistantMessage,
            'formAction' => route('ai.book-assistant.messages'),
            'inputName' => 'message',
        ]);
    }

    public function apiStore(BookAssistantRequest $request): JsonResponse
    {
        $message = $request->validated('message');
        $sessionId = $this->apiSessionId($request);
        $conversation = $this->resolveConversation($request, $sessionId);
        $userMessage = $this->storeUserMessage($conversation, $request, $message);
        $result = $this->safeRecommendation($message, $request, $conversation);
        $assistantMessage = $this->storeAssistantMessage($conversation, $result);
        $this->dispatchSummaryIfReady($conversation);

        return response()
            ->json([
                'conversation' => new AIConversationResource($conversation->fresh(['messages'])->loadCount('messages')),
                'message' => new AIMessageResource($userMessage),
                'assistant_message' => new AIMessageResource($assistantMessage),
                'recommendations' => $this->apiRecommendations($result),
                'provider_used' => $result['provider_used'] ?? 'none',
                'fallback_used' => (bool) ($result['fallback_used'] ?? false),
                'confidence' => (float) ($result['confidence'] ?? 0.0),
                'needs_human_help' => (bool) ($result['needs_human_help'] ?? true),
                'error_message' => $this->safeError($result['error_message'] ?? null),
                'session_id' => $sessionId,
            ])
            ->header('X-AI-Session-ID', $sessionId);
    }

    public function apiConversations(Request $request): JsonResponse
    {
        $sessionId = $this->apiSessionId($request);
        $conversations = $this->ownedConversationQuery($request, $sessionId)
            ->with(['messages' => fn ($query) => $query->latest()->limit(1)])
            ->withCount('messages')
            ->latest()
            ->get();

        return response()
            ->json(['data' => AIConversationResource::collection($conversations)])
            ->header('X-AI-Session-ID', $sessionId);
    }

    public function apiShow(Request $request, AIConversation $conversation): JsonResponse
    {
        $sessionId = $this->apiSessionId($request);
        $this->authorizeConversation($request, $conversation, $sessionId);

        return response()
            ->json([
                'data' => new AIConversationResource($conversation->load('messages')->loadCount('messages')),
            ])
            ->header('X-AI-Session-ID', $sessionId);
    }

    protected function resolveConversation(Request $request, string $sessionId): AIConversation
    {
        $conversationId = $request->integer('conversation_id') ?: null;

        if ($conversationId) {
            $conversation = AIConversation::query()->whereKey($conversationId)->firstOrFail();
            $this->authorizeConversation($request, $conversation, $sessionId);

            return $conversation;
        }

        return $this->latestConversation($request, $sessionId) ?? AIConversation::create([
            'user_id' => $request->user()?->id,
            'session_id' => $request->user() ? null : $sessionId,
            'title' => null,
            'status' => 'active',
            'metadata' => ['source' => $request->is('api/*') ? 'api' : 'web'],
        ]);
    }

    protected function latestConversation(Request $request, string $sessionId): ?AIConversation
    {
        return $this->ownedConversationQuery($request, $sessionId)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    protected function ownedConversationQuery(Request $request, string $sessionId)
    {
        return AIConversation::query()
            ->when($request->user(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->when(! $request->user(), fn ($query) => $query->whereNull('user_id')->where('session_id', $sessionId));
    }

    protected function authorizeConversation(Request $request, AIConversation $conversation, string $sessionId): void
    {
        $allowed = $request->user()
            ? (int) $conversation->user_id === (int) $request->user()->id
            : $conversation->user_id === null && hash_equals((string) $conversation->session_id, $sessionId);

        abort_unless($allowed, 403);
    }

    protected function storeUserMessage(AIConversation $conversation, Request $request, string $message): AIMessage
    {
        $this->titleConversation($conversation, $message);

        return $conversation->messages()->create([
            'user_id' => $request->user()?->id,
            'role' => 'user',
            'content' => $message,
            'metadata' => [],
        ]);
    }

    protected function storeAssistantMessage(AIConversation $conversation, array $result): AIMessage
    {
        return $conversation->messages()->create([
            'user_id' => $conversation->user_id,
            'role' => 'assistant',
            'content' => (string) ($result['answer'] ?? 'AI service is temporarily unavailable. Please try again later.'),
            'provider' => $result['provider_used'] ?? 'none',
            'model' => null,
            'confidence' => $result['confidence'] ?? 0.0,
            'metadata' => [
                'recommended_book_ids' => collect($result['recommendations'] ?? [])->pluck('book_id')->values()->all(),
                'fallback_used' => (bool) ($result['fallback_used'] ?? false),
                'latency_ms' => $result['latency_ms'] ?? null,
                'needs_human_help' => (bool) ($result['needs_human_help'] ?? true),
                'error_code' => $result['error_message'] ? 'assistant_error' : null,
            ],
        ]);
    }

    protected function safeRecommendation(string $message, Request $request, AIConversation $conversation): array
    {
        try {
            return $this->bookDiscovery->recommend(
                question: $message,
                userId: $request->user()?->id,
                conversationId: (string) $conversation->id,
            );
        } catch (Throwable) {
            return [
                'answer' => 'AI service is temporarily unavailable. Please try again later.',
                'recommendations' => [],
                'provider_used' => 'none',
                'fallback_used' => false,
                'confidence' => 0.0,
                'latency_ms' => 0,
                'needs_human_help' => true,
                'error_message' => 'AI service is temporarily unavailable.',
            ];
        }
    }

    protected function dispatchSummaryIfReady(AIConversation $conversation): void
    {
        if (! (bool) config('ai.queue_summaries', false)) {
            return;
        }

        if ($conversation->messages()->where('role', 'user')->count() < 3) {
            return;
        }

        try {
            ProcessAIConversationSummary::dispatch($conversation->id)->onQueue('ai-tasks');
        } catch (Throwable) {
            //
        }
    }

    protected function titleConversation(AIConversation $conversation, string $message): void
    {
        if ($conversation->title) {
            return;
        }

        $conversation->forceFill(['title' => Str::limit($message, 80, '')])->save();
    }

    protected function webSessionId(Request $request): string
    {
        return $request->session()->getId();
    }

    protected function apiSessionId(Request $request): string
    {
        $sessionId = trim((string) $request->header('X-AI-Session-ID'));

        return $sessionId !== '' ? $sessionId : 'api_'.Str::random(40);
    }

    protected function viewResult(array $result): array
    {
        return [
            'answer' => $result['answer'] ?? 'AI service is temporarily unavailable. Please try again later.',
            'intent' => [],
            'fallback_used' => (bool) ($result['fallback_used'] ?? false),
            'provider_used' => $result['provider_used'] ?? 'none',
            'confidence' => (float) ($result['confidence'] ?? 0.0),
            'needs_human_help' => (bool) ($result['needs_human_help'] ?? true),
            'error_message' => $this->safeError($result['error_message'] ?? null),
            'recommendations' => $this->viewRecommendations($result),
        ];
    }

    protected function viewRecommendations(array $result): array
    {
        return collect($result['recommendations'] ?? [])->map(function (array $item) {
            $book = $item['book'] ?? null;

            return [
                'id' => $item['book_id'] ?? $book?->id,
                'title' => $item['title'] ?? $book?->title,
                'author' => $book?->author,
                'price' => $book?->price,
                'stock' => $book?->stock,
                'slug' => $book?->slug,
                'format' => $book?->format,
                'category' => $book?->category ? ['name' => $book->category->name, 'slug' => $book->category->slug] : null,
                'reason' => $item['reason'] ?? 'Recommended from the active PageTurner catalog.',
            ];
        })->values()->all();
    }

    protected function apiRecommendations(array $result): array
    {
        return collect($result['recommendations'] ?? [])->map(function (array $item) {
            $book = $item['book'] ?? null;

            return [
                'book_id' => $item['book_id'] ?? $book?->id,
                'title' => $item['title'] ?? $book?->title,
                'author' => $book?->author,
                'price' => $book?->price !== null ? (float) $book->price : null,
                'stock' => $book?->stock,
                'category' => $book?->category?->name,
                'book_link' => $book?->slug ? route('books.show', $book->slug) : null,
                'reason' => $item['reason'] ?? null,
                'confidence' => isset($item['confidence']) ? (float) $item['confidence'] : null,
            ];
        })->values()->all();
    }

    protected function safeError(?string $error): ?string
    {
        return $error ? 'AI assistant could not complete the request safely.' : null;
    }
}
