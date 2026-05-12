<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                Orders
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Review customer orders, totals, and payment progress.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="soft-alert-success">
                {{ session('success') }}
            </div>
        @endif

        <section class="admin-panel">
            @if($orders->count())
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Action</th>
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

                                    $payment = strtolower($order->payment_status);
                                    $paymentClass = match($payment) {
                                        'paid' => 'status-pill status-pill-completed',
                                        'pending', 'unpaid' => 'status-pill status-pill-pending',
                                        default => 'status-pill status-pill-default',
                                    };
                                @endphp
                                <tr>
                                    <td class="font-semibold text-ink-900">{{ $order->order_number }}</td>
                                    <td>{{ $order->user?->email }}</td>
                                    <td><span class="{{ $statusClass }}">{{ ucfirst($order->status) }}</span></td>
                                    <td><span class="{{ $paymentClass }}">{{ ucfirst($order->payment_status) }}</span></td>
                                    <td>₱{{ number_format((float) $order->total_amount, 2) }}</td>
                                    <td>{{ $order->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $order) }}" class="nav-action-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-5">
                        {{ $orders->links() }}
                    </div>
                </div>
            @else
                <p class="text-sm text-ink-500">No orders found.</p>
            @endif
        </section>
    </div>
</x-app-layout>
