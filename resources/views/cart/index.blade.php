<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    My Cart
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Review items, update quantities, and continue to checkout.
                </p>
            </div>

            <a href="{{ route('books.index') }}" class="nav-action-secondary">
                Continue Shopping
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

        @if($cart->items->count())
            <div class="cart-layout">
                <div class="cart-main space-y-5">
                    @foreach($cart->items as $item)
                        <article class="cart-item-card">
                            <div class="cart-item-layout">
                                <div class="cart-item-book">
                                    <div class="flex items-start gap-4">
                                        @if($item->book?->cover_image)
                                            <img
                                                src="{{ asset('storage/' . $item->book->cover_image) }}"
                                                alt="{{ $item->book->title }}"
                                                class="cart-thumb"
                                            >
                                        @else
                                            <div class="cart-thumb-placeholder">
                                                No Image
                                            </div>
                                        @endif

                                        <div>
                                            <h3 class="text-lg font-semibold text-ink-900">
                                                {{ $item->book?->title }}
                                            </h3>
                                            <p class="mt-1 text-sm text-ink-500">
                                                {{ $item->book?->author }}
                                            </p>
                                            <p class="mt-2 text-sm text-ink-500">
                                                Stock: {{ $item->book?->stock }}
                                            </p>
                                            <p class="mt-3 text-lg font-bold text-brand-700">
                                                ₱{{ number_format((float) $item->unit_price, 2) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="cart-item-qty">
                                    <p class="text-sm font-semibold text-ink-800 mb-3">Quantity</p>

                                    <form
                                        method="POST"
                                        action="{{ route('cart-items.update', $item) }}"
                                        class="space-y-3"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <input
                                            type="number"
                                            name="quantity"
                                            min="1"
                                            max="{{ $item->book?->stock ?? 1 }}"
                                            value="{{ $item->quantity }}"
                                            class="form-input"
                                        >

                                        <button type="submit" class="btn-secondary-ui w-full sm:w-auto">
                                            Update
                                        </button>
                                    </form>
                                </div>

                                <div class="cart-item-total">
                                    <p class="text-sm font-semibold text-ink-800 mb-3">Total</p>
                                    <p class="text-xl font-bold text-ink-900">
                                        ₱{{ number_format((float) $item->unit_price * (int) $item->quantity, 2) }}
                                    </p>
                                </div>

                                <div class="cart-item-actions">
                                    <p class="text-sm font-semibold text-ink-800 mb-3">Actions</p>

                                    <div class="flex flex-col gap-3">
                                        <a
                                            href="{{ route('checkout.index', ['item' => $item->id]) }}"
                                            class="nav-action-primary text-center"
                                        >
                                            Checkout This
                                        </a>

                                        <form method="POST" action="{{ route('cart-items.destroy', $item) }}">
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                onclick="return confirm('Remove this item?')"
                                                class="btn-danger-ui w-full"
                                            >
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <aside class="cart-summary-card">
                    <h3 class="checkout-section-title">Cart Summary</h3>
                    <p class="checkout-section-text">
                        Review your subtotal and continue to full checkout.
                    </p>

                    <div class="mt-6 space-y-4">
                        <div class="summary-row">
                            <span>Items</span>
                            <span>{{ $cart->items->count() }}</span>
                        </div>

                        <div class="summary-total-row">
                            <span>Subtotal</span>
                            <span>₱{{ number_format((float) $subtotal, 2) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <a href="{{ route('checkout.index') }}" class="nav-action-primary w-full text-center">
                            Checkout All
                        </a>

                        <form method="POST" action="{{ route('cart.clear') }}">
                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                onclick="return confirm('Clear the entire cart?')"
                                class="btn-danger-ui w-full"
                            >
                                Clear Cart
                            </button>
                        </form>
                    </div>
                </aside>
            </div>
        @else
            <div class="empty-state-card">
                <h3 class="empty-state-title">Your cart is empty</h3>
                <p class="empty-state-text">
                    Add some books to your cart before checking out.
                </p>

                <div class="mt-5">
                    <a href="{{ route('books.index') }}" class="nav-action-primary">
                        Browse Books
                    </a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
