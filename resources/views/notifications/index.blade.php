<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                    Notifications
                </h2>
                <p class="mt-1 text-sm text-ink-500">
                    Review system updates, order changes, and activity alerts.
                </p>
            </div>

            <form method="POST" action="{{ route('notifications.read_all') }}">
                @csrf
                <button type="submit" class="nav-action-secondary">
                    Mark All Read
                </button>
            </form>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(session('success'))
            <div class="soft-alert-success">
                {{ session('success') }}
            </div>
        @endif

        <section class="content-card">
            @if($notifications->count())
                <div class="notification-list">
                    @foreach($notifications as $notification)
                        <article class="notification-card {{ is_null($notification->read_at) ? 'notification-card-unread' : '' }}">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div class="flex-1">
                                    <p class="notification-title">
                                        {{ $notification->data['message'] ?? 'Notification' }}
                                    </p>

                                    <div class="mt-2 space-y-1">
                                        @if(isset($notification->data['order_number']))
                                            <p class="notification-meta">Order #: {{ $notification->data['order_number'] }}</p>
                                        @endif

                                        @if(isset($notification->data['receipt_number']))
                                            <p class="notification-meta">Receipt #: {{ $notification->data['receipt_number'] }}</p>
                                        @endif

                                        @if(isset($notification->data['status']))
                                            <p class="notification-meta">Status: {{ ucfirst($notification->data['status']) }}</p>
                                        @endif

                                        @if(isset($notification->data['payment_status']))
                                            <p class="notification-meta">Payment: {{ ucfirst($notification->data['payment_status']) }}</p>
                                        @endif

                                        @if(isset($notification->data['customer_email']))
                                            <p class="notification-meta">Customer: {{ $notification->data['customer_email'] }}</p>
                                        @endif

                                        @if(isset($notification->data['rating']))
                                            <p class="notification-meta">Rating: {{ $notification->data['rating'] }}/5</p>
                                        @endif
                                    </div>

                                    <p class="notification-time">
                                        {{ $notification->created_at->format('M d, Y h:i A') }}
                                    </p>
                                </div>

                                @if(is_null($notification->read_at))
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="nav-action-primary">
                                            Mark Read
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $notifications->links() }}
                </div>
            @else
                <div class="empty-state-card !shadow-none !border-0 !p-0">
                    <h3 class="empty-state-title">No notifications yet</h3>
                    <p class="empty-state-text">
                        Notifications will appear here when there are updates to your account or orders.
                    </p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
