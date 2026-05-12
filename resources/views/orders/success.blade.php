<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                Order Placed
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Your order was submitted successfully.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="success-hero">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                <div class="success-icon">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7" />
                    </svg>
                </div>

                <div class="flex-1">
                    <h1 class="success-title">Your order was placed successfully</h1>
                    <p class="success-text">
                        Keep your order and receipt numbers for reference. You can also view this order later from your dashboard or orders page.
                    </p>

                    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="order-info-card">
                            <p class="order-info-label">Order Number</p>
                            <p class="order-info-value">{{ $order->order_number }}</p>
                        </div>

                        <div class="order-info-card">
                            <p class="order-info-label">Receipt Number</p>
                            <p class="order-info-value">{{ $order->receipt_number }}</p>
                        </div>

                        <div class="order-info-card">
                            <p class="order-info-label">Status</p>
                            <p class="order-info-value">{{ ucfirst($order->status) }}</p>
                        </div>

                        <div class="order-info-card">
                            <p class="order-info-label">Total</p>
                            <p class="order-info-value">₱{{ number_format((float) $order->total_amount, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-card">
            <h3 class="content-card-title">Items</h3>
            <p class="content-card-subtitle">
                Books included in this completed checkout.
            </p>

            <div class="mt-5 space-y-4">
                @foreach($order->items as $item)
                    <div class="rounded-2xl border border-sage-100 bg-sage-50 p-4">
                        <p class="font-semibold text-ink-900">{{ $item->book_title }}</p>
                        <p class="mt-1 text-sm text-ink-500">Author: {{ $item->book_author }}</p>
                        <p class="mt-1 text-sm text-ink-500">Quantity: {{ $item->quantity }}</p>
                        <p class="mt-1 text-sm text-ink-500">Unit Price: ₱{{ number_format((float) $item->unit_price, 2) }}</p>
                        <p class="mt-2 text-sm font-semibold text-ink-800">
                            Line Total: ₱{{ number_format((float) $item->line_total, 2) }}
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('books.index') }}" class="nav-action-primary">
                    Continue Shopping
                </a>

                <a href="{{ route('customer.dashboard') }}" class="nav-action-secondary">
                    Go to Dashboard
                </a>

                <a href="{{ route('orders.receipt', $order) }}" class="nav-action-secondary">
                    View Receipt
                </a>
            </div>
        </section>
    </div>
</x-app-layout>
