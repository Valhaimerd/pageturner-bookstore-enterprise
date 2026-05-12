<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    Order Details
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    View full order information, buyer details, and purchased items.
                </p>
            </div>

            <a href="{{ route('orders.index') }}" class="nav-action-secondary">
                Back to Orders
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @php
            $status = strtolower($order->status);
            $statusClass = match($status) {
                'pending' => 'status-pill status-pill-pending',
                'completed' => 'status-pill status-pill-completed',
                default => 'status-pill status-pill-default',
            };

            $paymentStatus = strtolower($order->payment_status);
            $paymentClass = match($paymentStatus) {
                'pending' => 'status-pill status-pill-pending',
                'paid', 'completed' => 'status-pill status-pill-completed',
                default => 'status-pill status-pill-default',
            };
        @endphp

        <section class="order-summary-grid">
            <div class="order-info-card">
                <p class="order-info-label">Order Number</p>
                <p class="order-info-value">{{ $order->order_number }}</p>
            </div>

            <div class="order-info-card">
                <p class="order-info-label">Receipt Number</p>
                <p class="order-info-value">{{ $order->receipt_number ?: '—' }}</p>
            </div>

            <div class="order-info-card">
                <p class="order-info-label">Order Status</p>
                <div class="mt-2">
                    <span class="{{ $statusClass }}">{{ ucfirst($order->status) }}</span>
                </div>
            </div>

            <div class="order-info-card">
                <p class="order-info-label">Total Amount</p>
                <p class="order-info-value">₱{{ number_format((float) $order->total_amount, 2) }}</p>
            </div>
        </section>

        <div class="order-layout">
            <section class="order-main-card">
                <h3 class="content-card-title">Purchased Items</h3>
                <p class="content-card-subtitle">
                    Breakdown of all books included in this order.
                </p>

                <div class="mt-5 ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Unit</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td class="font-semibold text-ink-900">{{ $item->book_title }}</td>
                                    <td>{{ $item->book_author }}</td>
                                    <td>₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>₱{{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="order-side-card">
                    <h3 class="content-card-title">Order Information</h3>
                    <div class="mt-4 detail-list">
                        <p><strong>Status:</strong> <span class="{{ $statusClass }}">{{ ucfirst($order->status) }}</span></p>
                        <p><strong>Payment Status:</strong> <span class="{{ $paymentClass }}">{{ ucfirst($order->payment_status) }}</span></p>
                        <p><strong>Total:</strong> ₱{{ number_format((float) $order->total_amount, 2) }}</p>
                        <p><strong>Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
                    </div>

                    <div class="mt-5">
                        <a href="{{ route('orders.receipt', $order) }}" class="nav-action-primary">
                            View Receipt
                        </a>
                    </div>
                </section>

                <section class="order-side-card">
                    <h3 class="content-card-title">Buyer</h3>
                    <div class="mt-4 detail-list">
                        <p><strong>Name:</strong> {{ $order->buyer_name }}</p>
                        <p><strong>Email:</strong> {{ $order->buyer_email }}</p>
                        <p><strong>Phone:</strong> {{ $order->buyer_phone ?: '—' }}</p>
                    </div>
                </section>

                <section class="order-side-card">
                    <h3 class="content-card-title">Delivery Address</h3>
                    <div class="mt-4 detail-list">
                        <p>{{ $order->address_line_1 }}</p>
                        @if($order->address_line_2)
                            <p>{{ $order->address_line_2 }}</p>
                        @endif
                        <p>{{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}</p>
                        <p>{{ $order->country }}</p>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
