<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                Admin Dashboard
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Monitor users, catalog, orders, revenue, and recent activity.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="admin-hero">
            <span class="dashboard-chip">Management Overview</span>
            <h1 class="admin-title mt-4">Store control center</h1>
            <p class="admin-subtitle">
                Manage categories, books, customer orders, and recent review activity from one place.
            </p>

            <div class="mt-6 admin-action-grid">
                <a href="{{ route('admin.categories.index') }}" class="admin-action-card">
                    <h3 class="admin-action-title">Manage Categories</h3>
                    <p class="admin-action-text">Create, update, and organize book categories.</p>
                </a>

                <a href="{{ route('admin.books.index') }}" class="admin-action-card">
                    <h3 class="admin-action-title">Manage Books</h3>
                    <p class="admin-action-text">Maintain the catalog, stock, pricing, and featured books.</p>
                </a>

                <a href="{{ route('admin.orders.index') }}" class="admin-action-card">
                    <h3 class="admin-action-title">Manage Orders</h3>
                    <p class="admin-action-text">Review orders, payment status, and customer information.</p>
                </a>

                <a href="{{ route('admin.data.index') }}" class="admin-action-card">
                    <h3 class="admin-action-title">Data Management</h3>
                    <p class="admin-action-text">Run imports, exports, backups, and operational reports.</p>
                </a>

                <a href="{{ route('admin.audits.index') }}" class="admin-action-card">
                    <h3 class="admin-action-title">Audit Logs</h3>
                    <p class="admin-action-text">Inspect sensitive changes and security-relevant activity.</p>
                </a>
            </div>
        </section>

        <section class="admin-stat-grid">
            <div class="metric-card">
                <p class="metric-label">Total Users</p>
                <p class="metric-value">{{ $totalUsers }}</p>
                <p class="metric-note">All registered accounts</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Customers</p>
                <p class="metric-value">{{ $totalCustomers }}</p>
                <p class="metric-note">Customer accounts only</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Books</p>
                <p class="metric-value">{{ $totalBooks }}</p>
                <p class="metric-note">Catalog entries</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Categories</p>
                <p class="metric-value">{{ $totalCategories }}</p>
                <p class="metric-note">Available groups</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Orders</p>
                <p class="metric-value">{{ $totalOrders }}</p>
                <p class="metric-note">Customer purchases</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Revenue</p>
                <p class="metric-value">₱{{ number_format((float) $totalRevenue, 2) }}</p>
                <p class="metric-note">Recorded total revenue</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 lg:grid-cols-4">
            <div class="metric-card">
                <p class="metric-label">Pending</p>
                <p class="metric-value">{{ $statusCounts['pending'] ?? 0 }}</p>
                <p class="metric-note">Awaiting action</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Processing</p>
                <p class="metric-value">{{ $statusCounts['processing'] ?? 0 }}</p>
                <p class="metric-note">Currently handled</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Completed</p>
                <p class="metric-value">{{ $statusCounts['completed'] ?? 0 }}</p>
                <p class="metric-note">Finished orders</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Cancelled</p>
                <p class="metric-value">{{ $statusCounts['cancelled'] ?? 0 }}</p>
                <p class="metric-note">Stopped orders</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card">
                <p class="metric-label">Imports Today</p>
                <p class="metric-value">{{ $recentImportCount }}</p>
                <p class="metric-note">Queued or completed transfer jobs</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Exports Today</p>
                <p class="metric-value">{{ $recentExportCount }}</p>
                <p class="metric-note">Generated data portability files</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">Latest Backup</p>
                <p class="metric-value">{{ ucfirst($latestBackupStatus) }}</p>
                <p class="metric-note">Most recent backup monitor result</p>
            </div>

            <div class="metric-card">
                <p class="metric-label">API Throttle Hits</p>
                <p class="metric-value">{{ $apiThrottleHits }}</p>
                <p class="metric-note">429 responses in the last 24 hours</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <a href="{{ route('admin.ai.index') }}" class="metric-card">
                <p class="metric-label">AI Requests Today</p>
                <p class="metric-value">{{ $aiInteractionsToday }}</p>
                <p class="metric-note">Book assistant interactions</p>
            </a>

            <a href="{{ route('admin.ai.index', ['fallback_only' => 1]) }}" class="metric-card">
                <p class="metric-label">AI Fallbacks Today</p>
                <p class="metric-value">{{ $aiFallbacksToday }}</p>
                <p class="metric-note">FakeAIProvider recoveries</p>
            </a>

            <div class="metric-card">
                <p class="metric-label">AI Cost Status</p>
                <p class="metric-value">₱0.00</p>
                <p class="metric-note">{{ number_format((float) $aiEstimatedCostCents, 2) }} estimated cents</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="admin-panel">
                <h3 class="admin-panel-title">Latest Orders</h3>
                <p class="admin-panel-subtitle">Most recent customer transactions.</p>

                <div class="mt-5">
                    @if($latestOrders->count())
                        <div class="ui-table-wrap">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($latestOrders as $order)
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
                                                <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-brand-700 hover:underline">
                                                    {{ $order->order_number }}
                                                </a>
                                            </td>
                                            <td>{{ $order->user?->email }}</td>
                                            <td><span class="{{ $statusClass }}">{{ ucfirst($order->status) }}</span></td>
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

            <div class="admin-panel">
                <h3 class="admin-panel-title">Latest Reviews</h3>
                <p class="admin-panel-subtitle">Recent customer feedback on books.</p>

                <div class="mt-5">
                    @if($latestReviews->count())
                        <div class="space-y-4">
                            @foreach($latestReviews as $review)
                                <div class="rounded-2xl border border-sage-100 bg-sage-50 p-4">
                                    <p class="font-semibold text-ink-900">{{ $review->book?->title }}</p>
                                    <p class="mt-1 text-sm text-ink-500">{{ $review->user?->name }} • {{ $review->rating }}/5</p>
                                    <p class="mt-2 text-sm leading-6 text-ink-600">{{ $review->comment ?: 'No comment.' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-5 text-sm text-ink-500">No reviews yet.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
