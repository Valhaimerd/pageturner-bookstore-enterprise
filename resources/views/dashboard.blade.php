<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                Dashboard
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Overview of your bookstore account.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if(auth()->user() && !auth()->user()->hasVerifiedEmail())
            <div class="soft-alert-warning">
                Your email is not verified yet. You need verification before checkout and reviews.
                <a class="ml-1 font-semibold underline" href="{{ route('verification.notice') }}">Verify now</a>
            </div>
        @endif

        <section class="dashboard-hero">
            <span class="dashboard-chip">Account Overview</span>
            <h1 class="dashboard-title mt-4">
                Welcome back, {{ auth()->user()->name }}.
            </h1>
            <p class="dashboard-subtitle">
                Use this space to browse books, manage your cart, review your orders, and update your profile details.
            </p>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a href="{{ route('books.index') }}" class="nav-action-primary">
                    Browse Books
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
            </div>
        </section>
    </div>
</x-app-layout>
