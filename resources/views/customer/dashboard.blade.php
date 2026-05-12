<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                Customer Dashboard
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Track your activity, orders, reviews, and spending in one place.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(auth()->user() && !auth()->user()->hasVerifiedEmail())
            <div class="soft-alert-warning">
                Your email is not verified yet. You must verify before checkout and reviews.
                <a class="ml-1 font-semibold underline" href="{{ route('verification.notice') }}">Verify now</a>
            </div>
        @endif

        <section class="dashboard-hero">
            <span class="dashboard-chip">Customer Summary</span>
            <h1 class="dashboard-title mt-4">
                Welcome, {{ auth()->user()->name }}.
            </h1>
            <p class="dashboard-subtitle">
                Here is your recent activity, order progress, total spending, and review history.
            </p>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a href="{{ route('books.index') }}" class="nav-action-primary">
                    Browse Books
                </a>

                <a href="{{ route('ai.assistant.index') }}" class="nav-action-secondary">
                    Ask AI Assistant
                </a>

                <a href="{{ route('cart.index') }}" class="nav-action-secondary">
                    View Cart
                </a>

                <a href="{{ route('orders.index') }}" class="nav-action-secondary">
                    My Orders
                </a>

                <a href="{{ route('profile.edit') }}" class="nav-action-secondary">
                    Edit Profile
                </a>

                <a href="{{ route('customer.exports.personal') }}" class="nav-action-secondary">
                    Export My Data
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
            <div class="metric-card">
                <p class="metric-label">Total Orders</p>
                <p class="metric-value">{{ $totalOrders }}</p>
                <p class="metric-note">All orders placed</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Pending</p>
                <p class="metric-value">{{ $pendingOrders }}</p>
                <p class="metric-note">Orders in progress</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Completed</p>
                <p class="metric-value">{{ $completedOrders }}</p>
                <p class="metric-note">Finished purchases</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Total Spent</p>
                <p class="metric-value">₱{{ number_format((float) $totalSpent, 2) }}</p>
                <p class="metric-note">Lifetime spending</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">My Reviews</p>
                <p class="metric-value">{{ $totalReviews }}</p>
                <p class="metric-note">Reviews submitted</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <a href="{{ route('books.index') }}" class="quick-link-card">
                <h3 class="quick-link-title">Explore the catalog</h3>
                <p class="quick-link-text">Browse available books and discover your next read.</p>
            </a>

            <a href="{{ route('orders.index') }}" class="quick-link-card">
                <h3 class="quick-link-title">Track your orders</h3>
                <p class="quick-link-text">Review order statuses, totals, and detailed purchase history.</p>
            </a>
        </section>

        <section class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <a href="{{ route('customer.exports.personal') }}" class="quick-link-card">
                <h3 class="quick-link-title">Download personal data</h3>
                <p class="quick-link-text">Export your profile, order, and review data as JSON.</p>
            </a>

            <form action="{{ route('customer.exports.orders') }}" method="POST" class="quick-link-card">
                @csrf
                <input type="hidden" name="format" value="pdf">
                <button type="submit" class="w-full text-left">
                    <h3 class="quick-link-title">Export order history</h3>
                    <p class="quick-link-text">Generate a PDF archive of your order history.</p>
                </button>
            </form>

            <a href="{{ route('customer.exports.reading') }}" class="quick-link-card">
                <h3 class="quick-link-title">Reading history</h3>
                <p class="quick-link-text">Export purchased books and submitted reviews.</p>
            </a>
        </section>

        @if($recentExports->count())
            <section class="content-card">
                <h3 class="content-card-title">Recent Data Exports</h3>
                <p class="content-card-subtitle">Completed generated files remain available for a limited time.</p>

                <div class="mt-5 ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Format</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentExports as $export)
                                <tr>
                                    <td>{{ str_replace('_', ' ', ucfirst($export->type)) }}</td>
                                    <td>{{ strtoupper($export->format) }}</td>
                                    <td>
                                        <span class="status-pill {{ $export->status === 'completed' ? 'status-pill-completed' : 'status-pill-default' }}">
                                            {{ ucfirst($export->status) }}
                                        </span>
                                    </td>
                                    <td>{{ optional($export->created_at)->format('M d, Y h:i A') }}</td>
                                    <td>
                                        @if($export->status === 'completed' && $export->path)
                                            <a href="{{ route('exports.download', $export) }}" class="nav-action-secondary">Download</a>
                                        @else
                                            <span class="text-ink-400">Processing</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="content-card">
                <h3 class="content-card-title">Recent Orders</h3>
                <p class="content-card-subtitle">Your latest purchases and current order statuses.</p>

                <div class="mt-5">
                    @if($recentOrders->count())
                        <div class="ui-table-wrap">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentOrders as $order)
                                        @php
                                            $status = strtolower($order->status);
                                            $statusClass = match($status) {
                                                'pending' => 'status-pill status-pill-pending',
                                                'completed' => 'status-pill status-pill-completed',
                                                default => 'status-pill status-pill-default',
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('orders.show', $order) }}" class="font-semibold text-brand-700 hover:underline">
                                                    {{ $order->order_number }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="{{ $statusClass }}">
                                                    {{ ucfirst($order->status) }}
                                                </span>
                                            </td>
                                            <td>₱{{ number_format((float) $order->total_amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="mt-5 text-sm text-ink-500">No orders yet.</p>
                    @endif
                </div>
            </div>

            <div class="content-card">
                <h3 class="content-card-title">Recent Reviews</h3>
                <p class="content-card-subtitle">Your latest ratings and comments on books.</p>

                <div class="mt-5">
                    @if($recentReviews->count())
                        <div class="space-y-4">
                            @foreach($recentReviews as $review)
                                <div class="rounded-2xl border border-sage-100 bg-sage-50 p-4">
                                    <p class="font-semibold text-ink-900">{{ $review->book?->title }}</p>
                                    <p class="mt-1 text-sm font-medium text-brand-700">Rating: {{ $review->rating }}/5</p>
                                    <p class="mt-2 text-sm leading-6 text-ink-600">{{ $review->comment ?: 'No comment.' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-ink-500">No reviews yet.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
