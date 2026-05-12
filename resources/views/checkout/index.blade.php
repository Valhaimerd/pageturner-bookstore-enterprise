<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    Checkout
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Complete your shipping and payment details before placing the order.
                </p>
            </div>

            <a href="{{ route('cart.index') }}" class="nav-action-secondary">
                Back to Cart
            </a>
        </div>
    </x-slot>

    <div class="checkout-layout">
        <section class="checkout-form-card">
            @if($errors->has('checkout'))
                <div class="soft-alert-danger mb-6">
                    {{ $errors->first('checkout') }}
                </div>
            @endif

            <div>
                <h3 class="checkout-section-title">Shipping and Buyer Information</h3>
                <p class="checkout-section-text">
                    Review your details carefully before placing the order.
                </p>
            </div>

            <form method="POST" action="{{ route('checkout.store') }}" class="checkout-group">
                @csrf

                @if($selectedItemId)
                    <input type="hidden" name="item_id" value="{{ $selectedItemId }}">
                @endif

                <div class="checkout-grid-2">
                    <div>
                        <label for="buyer_name" class="form-label">Buyer Name</label>
                        <input
                            id="buyer_name"
                            name="buyer_name"
                            type="text"
                            value="{{ old('buyer_name', $user->name) }}"
                            class="form-input"
                            required
                        >
                        @error('buyer_name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="buyer_email" class="form-label">Buyer Email</label>
                        <input
                            id="buyer_email"
                            name="buyer_email"
                            type="email"
                            value="{{ old('buyer_email', $user->email) }}"
                            class="form-input"
                            required
                        >
                        @error('buyer_email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="buyer_phone" class="form-label">Phone</label>
                    <input
                        id="buyer_phone"
                        name="buyer_phone"
                        type="text"
                        value="{{ old('buyer_phone', $user->phone) }}"
                        class="form-input"
                    >
                    @error('buyer_phone')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="address_line_1" class="form-label">Address Line 1</label>
                    <input
                        id="address_line_1"
                        name="address_line_1"
                        type="text"
                        value="{{ old('address_line_1', $user->default_address_line_1) }}"
                        class="form-input"
                        required
                    >
                    @error('address_line_1')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="address_line_2" class="form-label">Address Line 2</label>
                    <input
                        id="address_line_2"
                        name="address_line_2"
                        type="text"
                        value="{{ old('address_line_2', $user->default_address_line_2) }}"
                        class="form-input"
                    >
                    @error('address_line_2')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="checkout-grid-3">
                    <div>
                        <label for="city" class="form-label">City</label>
                        <input
                            id="city"
                            name="city"
                            type="text"
                            value="{{ old('city', $user->default_city) }}"
                            class="form-input"
                            required
                        >
                        @error('city')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="province" class="form-label">Province</label>
                        <input
                            id="province"
                            name="province"
                            type="text"
                            value="{{ old('province', $user->default_province) }}"
                            class="form-input"
                            required
                        >
                        @error('province')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="postal_code" class="form-label">Postal Code</label>
                        <input
                            id="postal_code"
                            name="postal_code"
                            type="text"
                            value="{{ old('postal_code', $user->default_postal_code) }}"
                            class="form-input"
                            required
                        >
                        @error('postal_code')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="country" class="form-label">Country</label>
                    <input
                        id="country"
                        name="country"
                        type="text"
                        value="{{ old('country', $user->default_country ?: 'Philippines') }}"
                        class="form-input"
                        required
                    >
                    @error('country')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select
                        id="payment_method"
                        name="payment_method"
                        class="form-input"
                        required
                    >
                        <option value="cash_on_delivery" {{ old('payment_method') === 'cash_on_delivery' ? 'selected' : '' }}>
                            Cash on Delivery
                        </option>
                        <option value="manual" {{ old('payment_method') === 'manual' ? 'selected' : '' }}>
                            Manual Payment
                        </option>
                    </select>
                    @error('payment_method')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notes" class="form-label">Notes</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        class="form-input"
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button type="submit" class="nav-action-primary">
                        Place Order
                    </button>
                </div>
            </form>
        </section>

        <aside class="checkout-summary-card">
            <h3 class="checkout-section-title">Order Summary</h3>
            <p class="checkout-section-text">
                Review the items included in this order.
            </p>

            <div class="mt-6 space-y-4">
                @foreach($items as $item)
                    <div class="order-line">
                        <p class="font-semibold text-ink-900">{{ $item->book?->title }}</p>
                        <p class="mt-1 text-sm text-ink-500">Author: {{ $item->book?->author }}</p>
                        <p class="mt-1 text-sm text-ink-500">Price: ₱{{ number_format((float) $item->unit_price, 2) }}</p>
                        <p class="mt-1 text-sm text-ink-500">Quantity: {{ $item->quantity }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink-800">
                            Line Total: ₱{{ number_format((float) $item->unit_price * (int) $item->quantity, 2) }}
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 space-y-3">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₱{{ number_format((float) $subtotal, 2) }}</span>
                </div>

                <div class="summary-row">
                    <span>Shipping</span>
                    <span>₱0.00</span>
                </div>

                <div class="summary-row">
                    <span>Tax</span>
                    <span>₱0.00</span>
                </div>

                <div class="summary-total-row">
                    <span>Total</span>
                    <span>₱{{ number_format((float) $subtotal, 2) }}</span>
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
