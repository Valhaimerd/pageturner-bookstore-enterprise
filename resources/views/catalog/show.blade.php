<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    Book Details
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    View book information, availability, and reader reviews.
                </p>
            </div>

            <a href="{{ route('books.index') }}" class="nav-action-secondary">
                Back to Catalog
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="soft-alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="soft-alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <section class="book-detail-layout">
            <div class="book-detail-cover-card">
                @if($book->cover_image)
                    <img
                        src="{{ asset('storage/' . $book->cover_image) }}"
                        alt="{{ $book->title }}"
                        class="book-detail-cover"
                    >
                @else
                    <div class="book-detail-placeholder">
                        No Image
                    </div>
                @endif
            </div>

            <div class="book-detail-info-card">
                @if($book->category?->name)
                    <span class="book-category-pill">
                        {{ $book->category->name }}
                    </span>
                @endif

                <h1 class="mt-4 text-3xl font-bold tracking-tight text-ink-900">
                    {{ $book->title }}
                </h1>

                <p class="mt-2 text-lg text-ink-600">
                    by {{ $book->author }}
                </p>

                <p class="mt-4 text-3xl font-bold text-brand-700">
                    ₱{{ number_format((float) $book->price, 2) }}
                </p>

                <div class="detail-stat-grid">
                    <div class="detail-stat-box">
                        <p class="detail-stat-label">Stock</p>
                        <p class="detail-stat-value">
                            @if($book->stock > 0)
                                <span class="stock-good">{{ $book->stock }} available</span>
                            @else
                                <span class="stock-bad">Out of stock</span>
                            @endif
                        </p>
                    </div>

                    <div class="detail-stat-box">
                        <p class="detail-stat-label">Average Rating</p>
                        <p class="detail-stat-value">
                            {{ $reviewCount ? $averageRating : 'No ratings yet' }}
                        </p>
                    </div>

                    <div class="detail-stat-box">
                        <p class="detail-stat-label">Total Reviews</p>
                        <p class="detail-stat-value">{{ $reviewCount }}</p>
                    </div>

                    @if($book->isbn)
                        <div class="detail-stat-box">
                            <p class="detail-stat-label">ISBN</p>
                            <p class="detail-stat-value">{{ $book->isbn }}</p>
                        </div>
                    @endif

                    @if($book->published_at)
                        <div class="detail-stat-box">
                            <p class="detail-stat-label">Published</p>
                            <p class="detail-stat-value">{{ $book->published_at->format('F d, Y') }}</p>
                        </div>
                    @endif
                </div>

                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-ink-900">Description</h3>
                    <p class="mt-3 whitespace-pre-line text-sm leading-7 text-ink-600">
                        {{ $book->description ?: 'No description available.' }}
                    </p>
                </div>

                @auth
                    @if(auth()->user()->isCustomer())
                        <div class="buy-panel">
                            @if($book->stock > 0)
                                <div class="space-y-4">
                                    <form method="POST" action="{{ route('cart.store', $book->slug) }}" class="space-y-3">
                                        @csrf

                                        <div class="max-w-xs">
                                            <label for="cart_quantity" class="form-label">
                                                Quantity
                                            </label>
                                            <input
                                                id="cart_quantity"
                                                name="quantity"
                                                type="number"
                                                min="1"
                                                max="{{ $book->stock }}"
                                                value="1"
                                                class="form-input"
                                            >
                                        </div>

                                        <div class="flex flex-wrap gap-3">
                                            <button type="submit" class="nav-action-primary">
                                                Add to Cart
                                            </button>
                                        </div>
                                    </form>

                                    <form method="POST" action="{{ route('cart.buy_now', $book->slug) }}">
                                        @csrf
                                        <input type="hidden" name="quantity" value="1">

                                        <button type="submit" class="nav-action-secondary">
                                            Buy Now
                                        </button>
                                    </form>
                                </div>
                            @else
                                <p class="stock-bad">Out of stock</p>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="mt-6">
                        <a href="{{ route('login') }}" class="nav-action-primary">
                            Login to Buy
                        </a>
                    </div>
                @endauth
            </div>
        </section>

        <section class="content-card">
            <h3 class="content-card-title">Reviews</h3>
            <p class="content-card-subtitle">
                Ratings and comments from readers.
            </p>

            <div class="mt-5 space-y-5">
                @auth
                    @if(auth()->user()->isCustomer())
                        @if(!$purchased)
                            <div class="soft-alert-warning">
                                You can only review this book after purchasing it.
                            </div>
                        @elseif($canReview)
                            <div class="review-form-card">
                                <p class="text-base font-semibold text-ink-900 mb-4">
                                    {{ $userReview ? 'Edit Your Review' : 'Write a Review' }}
                                </p>

                                <form
                                    method="POST"
                                    action="{{ $userReview ? route('reviews.update', $userReview) : route('reviews.store', $book->slug) }}"
                                    class="space-y-4"
                                >
                                    @csrf
                                    @if($userReview)
                                        @method('PUT')
                                    @endif

                                    <div class="max-w-xs">
                                        <label class="form-label">Rating (1-5)</label>
                                        <input
                                            type="number"
                                            name="rating"
                                            min="1"
                                            max="5"
                                            value="{{ old('rating', $userReview?->rating ?? 5) }}"
                                            class="form-input"
                                            required
                                        >
                                        @error('rating')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="form-label">Comment</label>
                                        <textarea
                                            name="comment"
                                            rows="4"
                                            class="form-input"
                                        >{{ old('comment', $userReview?->comment) }}</textarea>
                                        @error('comment')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex flex-wrap gap-3">
                                        <button type="submit" class="btn-primary-ui">
                                            {{ $userReview ? 'Update Review' : 'Submit Review' }}
                                        </button>
                                    </div>
                                </form>

                                @if($userReview)
                                    <form method="POST" action="{{ route('reviews.destroy', $userReview) }}" class="mt-3">
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            onclick="return confirm('Delete your review?')"
                                            class="btn-danger-ui"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    @endif
                @endauth

                @forelse($book->reviews as $review)
                    <div class="review-card">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="font-semibold text-ink-900">{{ $review->user?->name }}</p>
                            <p class="text-sm text-ink-500">{{ $review->created_at->format('M d, Y') }}</p>
                        </div>

                        <p class="mt-2 text-sm font-semibold text-brand-700">
                            Rating: {{ $review->rating }}/5
                        </p>

                        <p class="mt-3 text-sm leading-6 text-ink-600">
                            {{ $review->comment ?: 'No comment.' }}
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-ink-500">No reviews yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
