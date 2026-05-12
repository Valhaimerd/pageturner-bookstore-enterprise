<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    My Orders
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Review your order history, status, receipts, and totals.
                </p>
            </div>

            <a href="{{ route('books.index') }}" class="nav-action-primary">
                Shop Books
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if($orders->count())
        <section class="content-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="content-card-title">Order History</h3>
                        <p class="content-card-subtitle">
                            View all your orders and open their receipts anytime.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('customer.exports.orders') }}" class="flex gap-2">
                        @csrf
                        <button type="submit" name="format" value="csv" class="nav-action-secondary">Export CSV</button>
                        <button type="submit" name="format" value="xlsx" class="nav-action-secondary">Export XLSX</button>
                        <button type="submit" name="format" value="pdf" class="nav-action-primary">Export PDF</button>
                    </form>
                </div>

                <div class="mt-5 ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Receipt #</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                @php
                                    $status = strtolower($order->status);
                                    $statusClass = match($status) {
                                        'pending' => 'status-pill status-pill-pending',
                                        'completed' => 'status-pill status-pill-completed',
                                        default => 'status-pill status-pill-default',
                                    };
                                @endphp
                                <tr>
                                    <td class="font-semibold text-ink-900">{{ $order->order_number }}</td>
                                    <td>{{ $order->receipt_number ?: '—' }}</td>
                                    <td>{{ $order->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <span class="{{ $statusClass }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td>₱{{ number_format((float) $order->total_amount, 2) }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <a href="{{ route('orders.show', $order) }}" class="nav-action-primary">
                                                View
                                            </a>

                                            <a href="{{ route('orders.receipt', $order) }}" class="nav-action-secondary">
                                                Receipt
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    {{ $orders->links() }}
                </div>
            </section>
        @else
            <div class="empty-state-card">
                <h3 class="empty-state-title">No orders yet</h3>
                <p class="empty-state-text">
                    Your orders will appear here after checkout.
                </p>

                <div class="mt-5">
                    <a href="{{ route('books.index') }}" class="nav-action-primary">
                        Start Shopping
                    </a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
