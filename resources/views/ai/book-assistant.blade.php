<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                AI Book Assistant
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Ask PageTurner for grounded recommendations from the current catalog.
            </p>
        </div>
    </x-slot>

    @php
        $providerName = strtolower((string) ($result['provider_used'] ?? 'openai'));
        $providerLabel = match ($providerName) {
            'openai' => 'OpenAI',
            'gemini' => 'Gemini',
            'ollama' => 'Ollama',
            'fake' => 'Fake',
            'local' => 'Local',
            'none' => 'Unavailable',
            default => ucfirst($providerName),
        };
        $demoPrompts = [
            'I want something inspiring about friendship',
            'Recommend beginner Laravel books',
            'Find books under 500 pesos about programming',
            'What should I read after learning PHP?',
        ];
    @endphp

    <div
        class="ai-chat-shell"
        x-data="{ message: @js(old('message', $prompt ?? '')), loading: false }"
    >
        <section class="ai-chat-main">
            <div class="soft-alert-info">
                AI-generated suggestions. Please verify book details before buying.
            </div>

            @if($errors->any())
                <div class="soft-alert-danger" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            @if(!empty($result['error_message']))
                <div class="soft-alert-danger" role="alert">
                    {{ $result['error_message'] }}
                </div>
            @endif

            <div class="ai-message-list" aria-live="polite">
                @forelse($messages as $message)
                    <article class="{{ $message->role === 'user' ? 'ai-message-user' : 'ai-message-assistant' }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold uppercase tracking-[0.12em] text-ink-500">
                                {{ $message->role === 'user' ? 'You' : 'Assistant' }}
                            </span>

                            @if($message->role === 'assistant' && $message->provider)
                                <span class="status-pill status-pill-default">
                                    {{ match ($message->provider) {
                                        'openai' => 'OpenAI',
                                        'gemini' => 'Gemini',
                                        default => ucfirst($message->provider),
                                    } }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-2 text-sm leading-6 text-ink-700">{{ $message->content }}</p>
                    </article>
                @empty
                    <div class="empty-state-card">
                        <h3 class="empty-state-title">Ask for your next book</h3>
                        <p class="empty-state-text">
                            Describe a mood, topic, author, category, or learning goal.
                        </p>
                    </div>
                @endforelse
            </div>

            <form
                method="POST"
                action="{{ route('ai.book-assistant.messages') }}"
                class="ai-chat-form"
                x-on:submit="loading = true"
            >
                @csrf

                @if($conversation)
                    <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
                @endif

                <div>
                    <label for="message" class="form-label">Message</label>
                    <textarea
                        id="message"
                        name="message"
                        rows="4"
                        maxlength="1000"
                        class="admin-textarea"
                        placeholder="Example: Recommend beginner Laravel books"
                        x-model="message"
                        required
                    ></textarea>
                </div>

                <div class="flex flex-wrap gap-2" aria-label="Demo prompts">
                    @foreach($demoPrompts as $demoPrompt)
                        <button
                            type="button"
                            class="ai-demo-prompt"
                            x-on:click="message = @js($demoPrompt)"
                        >
                            {{ $demoPrompt }}
                        </button>
                    @endforeach
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <button type="submit" class="btn-primary-ui" x-bind:disabled="loading">
                        <span x-show="!loading">Send</span>
                        <span x-show="loading" style="display: none;">Sending...</span>
                    </button>

                    <p class="text-sm text-ink-500" x-show="loading" style="display: none;" role="status">
                        Loading recommendations...
                    </p>
                </div>
            </form>
        </section>

        <aside class="ai-chat-side">
            @if($result)
                <section class="ai-answer-panel">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="dashboard-chip">Assistant answer</span>
                        <span class="status-pill status-pill-completed">{{ $providerLabel }}</span>

                        @if($result['fallback_used'])
                            <span class="status-pill status-pill-pending">Fallback used</span>
                        @endif
                    </div>

                    <p class="mt-4 text-sm leading-6 text-ink-700">{{ $result['answer'] }}</p>

                    @if($result['needs_human_help'])
                        <p class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Confidence is low. A staff member may need to help verify the best match.
                        </p>
                    @endif
                </section>

                <section class="ai-recommendation-panel">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="content-card-title">Recommended books</h3>
                        <span class="text-xs font-semibold text-ink-500">
                            {{ count($result['recommendations']) }} result{{ count($result['recommendations']) === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4">
                        @forelse($result['recommendations'] as $book)
                            <article class="ai-book-card">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <span class="book-category-pill">{{ $book['category']['name'] ?? 'Catalog' }}</span>
                                        <h4 class="book-title">{{ $book['title'] }}</h4>
                                        <p class="book-author">by {{ $book['author'] }}</p>
                                    </div>
                                    <p class="book-price">&#8369;{{ number_format((float) $book['price'], 2) }}</p>
                                </div>

                                <p class="mt-4 text-sm leading-6 text-ink-600">{{ $book['reason'] }}</p>

                                <div class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                                    <div class="detail-stat-box">
                                        <p class="detail-stat-label">Stock</p>
                                        <p class="detail-stat-value">{{ $book['stock'] }}</p>
                                    </div>
                                    <div class="detail-stat-box">
                                        <p class="detail-stat-label">Category</p>
                                        <p class="detail-stat-value">{{ $book['category']['name'] ?? 'Catalog' }}</p>
                                    </div>
                                </div>

                                @if(!empty($book['slug']))
                                    <div class="mt-5">
                                        <a href="{{ route('books.show', $book['slug']) }}" class="nav-action-primary">View Book</a>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="empty-state-card">
                                <h3 class="empty-state-title">No recommendations yet</h3>
                                <p class="empty-state-text">Try a more specific topic, category, or learning goal.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @else
                <section class="panel-card p-6">
                    <span class="dashboard-chip">How it works</span>
                    <h3 class="mt-4 content-card-title">Grounded catalog recommendations</h3>
                    <p class="mt-2 text-sm leading-6 text-ink-500">
                        The assistant retrieves real active books first, then uses Gemini or OpenAI with Ollama and fake fallback providers to explain which books match your request.
                    </p>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
