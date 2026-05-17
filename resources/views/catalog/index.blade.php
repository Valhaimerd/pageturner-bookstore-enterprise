<x-app-layout>
    <x-slot name="header">
        <div class="catalog-header-card">
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight text-center">
                Book Catalog
            </h2>
            <p class="mt-1 text-sm text-ink-500 text-center">
                Browse available books by category and view their details.
            </p>
        </div>
    </x-slot>

    <div class="catalog-page-wrap space-y-6">
        <section class="store-toolbar catalog-toolbar-wrap">
            <div>
                <h3 class="store-toolbar-title">Find your next read</h3>
                <p class="store-toolbar-text">
                    Search by keyword, author, ISBN, or category.
                </p>
                <div class="mt-4 rounded-2xl border border-brand-100 bg-brand-50 p-4">
                    <p class="text-sm font-semibold text-ink-900">Need help choosing?</p>
                    <p class="mt-1 text-sm text-ink-600">Ask by mood, topic, author, category, or learning goal.</p>
                    <div class="mt-3">
                        <a href="{{ route('ai.book-assistant.index') }}" class="nav-action-secondary">
                            Ask AI for book recommendations
                        </a>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('books.index') }}" class="filter-form">
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    class="filter-select"
                    placeholder="Search title, author, ISBN, or description"
                    aria-label="Search books"
                >

                <select name="category" class="filter-select">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->slug }}" {{ $selectedCategory === $category->slug ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="nav-action-primary">
                    Filter
                </button>

                <a href="{{ route('books.index') }}" class="nav-action-secondary">
                    Reset
                </a>
            </form>
        </section>

        @if($books->count())
            <section class="catalog-grid">
                @foreach($books as $book)
                    <article class="book-card">
                        @if($book->cover_image)
                            <img
                                src="{{ asset('storage/' . $book->cover_image) }}"
                                alt="{{ $book->title }}"
                                class="book-card-media"
                            >
                        @else
                            <div class="book-card-placeholder">
                                No Image
                            </div>
                        @endif

                        <div class="book-card-body">
                            @if($book->category?->name)
                                <span class="book-category-pill">
                                    {{ $book->category->name }}
                                </span>
                            @endif

                            <h3 class="book-title">{{ $book->title }}</h3>
                            <p class="book-author">by {{ $book->author }}</p>

                            <p class="book-price">
                                ₱{{ number_format((float) $book->price, 2) }}
                            </p>

                            <p class="book-meta">
                                Stock: {{ $book->stock }}
                            </p>

                            <div class="mt-5">
                                <a href="{{ route('books.show', $book->slug) }}" class="nav-action-primary">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </section>

            <div class="pt-2">
                {{ $books->links() }}
            </div>
        @else
            <div class="empty-state-card">
                <h3 class="empty-state-title">No books found</h3>
                <p class="empty-state-text">
                    Try another search term, change the selected category, or reset the filters.
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
