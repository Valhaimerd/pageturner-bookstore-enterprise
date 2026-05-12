<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    Order Detail
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Update order status, payment status, and review customer details.
                </p>
            </div>

            <a href="{{ route('admin.orders.index') }}" class="nav-action-secondary">
                Back
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="soft-alert-success">
                {{ session('success') }}
            </div>
        @endif

        @php
            $status = strtolower($order->status);
            $statusClass = match($status) {
                'pending' => 'status-pill status-pill-pending',
                'completed' => 'status-pill status-pill-completed',
                default => 'status-pill status-pill-default',
            };

            $payment = strtolower($order->payment_status);
            $paymentClass = match($payment) {
                'paid' => 'status-pill status-pill-completed',
                'pending', 'unpaid' => 'status-pill status-pill-pending',
                default => 'status-pill status-pill-default',
            };
        @endphp

        <section class="order-summary-grid">
            <div class="order-info-card">
                <p class="order-info-label">Order #</p>
                <p class="order-info-value">{{ $order->order_number }}</p>
            </div>

            <div class="order-info-card">
                <p class="order-info-label">Receipt #</p>
                <p class="order-info-value">{{ $order->receipt_number ?: '—' }}</p>
            </div>

            <div class="order-info-card">
                <p class="order-info-label">Status</p>
                <div class="mt-2">
                    <span class="{{ $statusClass }}">{{ ucfirst($order->status) }}</span>
                </div>
            </div>

            <div class="order-info-card">
                <p class="order-info-label">Payment</p>
                <div class="mt-2">
                    <span class="{{ $paymentClass }}">{{ ucfirst($order->payment_status) }}</span>
                </div>
            </div>
        </section>

        <div class="order-layout">
            <section class="order-main-card space-y-6">
                <div>
                    <h3 class="content-card-title">Order Information</h3>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="detail-list">
                            <p><strong>Order #:</strong> {{ $order->order_number }}</p>
                            <p><strong>Receipt #:</strong> {{ $order->receipt_number ?: '—' }}</p>
                            <p><strong>Total:</strong> ₱{{ number_format((float) $order->total_amount, 2) }}</p>
                            <p><strong>Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
                        </div>

                        <div class="detail-list">
                            <p><strong>Customer:</strong> {{ $order->user?->name }} ({{ $order->user?->email }})</p>
                            <p><strong>Buyer:</strong> {{ $order->buyer_name }}</p>
                            <p><strong>Buyer Email:</strong> {{ $order->buyer_email }}</p>
                            <p><strong>Buyer Phone:</strong> {{ $order->buyer_phone ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-sage-100 pt-6">
                    <h3 class="content-card-title">Update Order</h3>

                    <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-3">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input" required>
                                @foreach(['pending','processing','completed','cancelled'] as $s)
                                    <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>
                                        {{ ucfirst($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Payment Status</label>
                            <select name="payment_status" class="form-input" required>
                                @foreach(['unpaid','paid','refunded'] as $p)
                                    <option value="{{ $p }}" {{ $order->payment_status === $p ? 'selected' : '' }}>
                                        {{ ucfirst($p) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="btn-primary-ui w-full md:w-auto">
                                Update Order
                            </button>
                        </div>
                    </form>
                </div>

                <div class="border-t border-sage-100 pt-6">
                    <h3 class="content-card-title">Address</h3>
                    <div class="mt-4 detail-list">
                        <p>{{ $order->address_line_1 }}</p>
                        @if($order->address_line_2)<p>{{ $order->address_line_2 }}</p>@endif
                        <p>{{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}</p>
                        <p>{{ $order->country }}</p>
                    </div>
                </div>
            </section>

            <aside class="order-side-card">
                <h3 class="content-card-title">Items</h3>
                <p class="content-card-subtitle">Books included in this order.</p>

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
            </aside>
        </div>
    </div>
</x-app-layout>
