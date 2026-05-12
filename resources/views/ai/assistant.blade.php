<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                PageTurner AI Book Discovery
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Ask by mood, topic, subject, category, or learning goal.
            </p>
        </div>
    </x-slot>

    <div class="ai-assistant-layout">
        <section class="ai-query-panel">
            @if($errors->any())
                <div class="soft-alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ $formAction ?? route('ai.assistant.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="prompt" class="form-label">What would you like to read?</label>
                    <textarea
                        id="prompt"
                        name="{{ $inputName ?? 'prompt' }}"
                        rows="5"
                        class="admin-textarea"
                        placeholder="Example: I want a beginner-friendly book about Laravel and APIs."
                    >{{ old($inputName ?? 'prompt', $prompt) }}</textarea>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <button type="submit" class="btn-primary-ui">Ask Assistant</button>
                    <a href="{{ route('books.index') }}" class="btn-secondary-ui">Browse Catalog</a>
                </div>
            </form>
        </section>

        @if($result)
            <section class="ai-result-panel">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <span class="dashboard-chip">AI Response</span>
                        <p class="mt-3 text-sm leading-6 text-ink-700">{{ $result['answer'] }}</p>
                    </div>

                    <div class="text-sm text-ink-500">
                        @if($result['fallback_used'])
                            <span class="status-pill status-pill-pending">Offline fallback used</span>
                        @else
                            <span class="status-pill status-pill-completed">Ollama response</span>
                        @endif
                    </div>
                </div>

                @if(!empty($result['intent']))
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach($result['intent'] as $key => $value)
                            @if($value)
                                <span class="ai-intent-chip">{{ str_replace('_', ' ', ucfirst($key)) }}: {{ $value }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
                    @forelse($result['recommendations'] as $book)
                        <article class="ai-book-card">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <span class="book-category-pill">{{ $book['category']['name'] ?? 'Catalog' }}</span>
                                    <h3 class="book-title">{{ $book['title'] }}</h3>
                                    <p class="book-author">by {{ $book['author'] }}</p>
                                </div>
                                <p class="book-price">₱{{ number_format((float) $book['price'], 2) }}</p>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-ink-600">{{ $book['reason'] }}</p>

                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div class="detail-stat-box">
                                    <p class="detail-stat-label">Stock</p>
                                    <p class="detail-stat-value">{{ $book['stock'] }}</p>
                                </div>
                                <div class="detail-stat-box">
                                    <p class="detail-stat-label">Format</p>
                                    <p class="detail-stat-value">{{ ucfirst((string) $book['format']) }}</p>
                                </div>
                            </div>

                            <div class="mt-5">
                                <a href="{{ route('books.show', $book['slug']) }}" class="nav-action-primary">View Book</a>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state-card lg:col-span-2">
                            <h3 class="empty-state-title">No matching books found</h3>
                            <p class="empty-state-text">Try a broader topic, category, mood, or learning goal.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
