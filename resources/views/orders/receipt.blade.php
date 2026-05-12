<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    Receipt
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Printable receipt for this order.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button onclick="window.print()" class="nav-action-primary">
                    Print
                </button>

                <a href="{{ route('orders.show', $order) }}" class="nav-action-secondary">
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto">
        <section class="receipt-card space-y-6">
            <div class="receipt-brand">
                <p class="receipt-brand-title">Page<span class="brand-highlight">Turner</span></p>
                <p class="receipt-brand-subtitle">Official Receipt</p>
            </div>

            <div class="receipt-divider"></div>

            <div class="receipt-meta-grid">
                <p><strong>Receipt #:</strong> {{ $order->receipt_number }}</p>
                <p><strong>Order #:</strong> {{ $order->order_number }}</p>
                <p><strong>Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
                <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
            </div>

            <div class="receipt-divider"></div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <p class="receipt-section-title">Billed To</p>
                    <div class="mt-3 detail-list">
                        <p>{{ $order->buyer_name }}</p>
                        <p>{{ $order->buyer_email }}</p>
                        <p>{{ $order->buyer_phone ?: '—' }}</p>
                    </div>
                </div>

                <div>
                    <p class="receipt-section-title">Address</p>
                    <div class="mt-3 detail-list">
                        <p>{{ $order->address_line_1 }}</p>
                        @if($order->address_line_2)
                            <p>{{ $order->address_line_2 }}</p>
                        @endif
                        <p>{{ $order->city }}, {{ $order->province }} {{ $order->postal_code }}</p>
                        <p>{{ $order->country }}</p>
                    </div>
                </div>
            </div>

            <div class="receipt-divider"></div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Unit</th>
                            <th>Qty</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>
                                    <p class="font-semibold text-ink-900">{{ $item->book_title }}</p>
                                    <p class="text-sm text-ink-500">{{ $item->book_author }}</p>
                                </td>
                                <td>₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>₱{{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="receipt-total-box space-y-2 text-sm">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₱{{ number_format((float) $order->subtotal, 2) }}</span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span>₱{{ number_format((float) $order->shipping_fee, 2) }}</span>
                </div>
                <div class="summary-row">
                    <span>Tax</span>
                    <span>₱{{ number_format((float) $order->tax_amount, 2) }}</span>
                </div>
                <div class="summary-total-row">
                    <span>Total</span>
                    <span>₱{{ number_format((float) $order->total_amount, 2) }}</span>
                </div>
            </div>

            <p class="text-center text-xs text-ink-500">
                This is a system-generated receipt.
            </p>
        </section>
    </div>
</x-app-layout>
