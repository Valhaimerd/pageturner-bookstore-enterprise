<nav x-data="{ open: false }" class="nav-shell">
    <div class="page-container">
        <div class="flex h-20 items-center justify-between gap-4">
            <div class="flex items-center gap-8">
                <div class="shrink-0">
                    <a href="{{ route('home') }}" class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white shadow-soft ring-1 ring-sage-100">
                            <x-application-logo class="h-6 w-6 fill-current text-brand-700" />
                        </span>
                        <div class="hidden sm:block">
                            <span class="brand-logo-text text-xl">
                                Page<span class="brand-highlight">Turner</span>
                            </span>
                            <p class="text-xs text-ink-500">Books, orders, and account</p>
                        </div>
                    </a>
                </div>

                <div class="hidden items-center gap-2 xl:flex">
                    <x-nav-link :href="route('home')" :active="request()->routeIs('home')">
                        Home
                    </x-nav-link>

                    <x-nav-link :href="route('books.index')" :active="request()->routeIs('books.*')">
                        Books
                    </x-nav-link>

                    <x-nav-link :href="route('ai.book-assistant.index')" :active="request()->routeIs('ai.book-assistant.*')">
                        AI Book Assistant
                    </x-nav-link>

                    @auth
                        <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                            Notifications
                        </x-nav-link>

                        @if(auth()->user()->isAdmin())
                            <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                                Admin Dashboard
                            </x-nav-link>

                            <x-nav-link :href="route('admin.orders.index')" :active="request()->routeIs('admin.orders.*')">
                                Orders
                            </x-nav-link>

                            <x-nav-link :href="route('admin.data.index')" :active="request()->routeIs('admin.data.*')">
                                Data
                            </x-nav-link>

                            <x-nav-link :href="route('admin.audits.index')" :active="request()->routeIs('admin.audits.*')">
                                Audits
                            </x-nav-link>

                            <x-nav-link :href="route('admin.ai.index')" :active="request()->routeIs('admin.ai.*')">
                                AI Logs
                            </x-nav-link>

                            <x-nav-link :href="route('admin.ai-monitoring.index')" :active="request()->routeIs('admin.ai-monitoring.*')">
                                AI Monitoring
                            </x-nav-link>
                        @else
                            <x-nav-link :href="route('customer.dashboard')" :active="request()->routeIs('customer.*')">
                                Customer Dashboard
                            </x-nav-link>

                            <x-nav-link :href="route('cart.index')" :active="request()->routeIs('cart.*') || request()->routeIs('cart-items.*')">
                                Cart
                            </x-nav-link>

                            <x-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')">
                                Orders
                            </x-nav-link>
                        @endif
                    @endauth
                </div>
            </div>

            <div class="hidden items-center gap-3 sm:flex">
                @auth
                    <div class="hidden lg:flex">
                        <span class="stat-badge">
                            Unread: {{ auth()->user()->unreadNotifications()->count() }}
                        </span>
                    </div>

                    <x-dropdown align="right" width="64">
                        <x-slot name="trigger">
                            <button class="user-chip">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </span>

                                <span class="hidden md:block text-left">
                                    <span class="block text-sm font-semibold text-ink-800 leading-tight">
                                        {{ auth()->user()->name }}
                                    </span>
                                    <span class="block text-xs text-ink-500 leading-tight">
                                        Account menu
                                    </span>
                                </span>

                                <svg class="h-4 w-4 text-ink-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.51a.75.75 0 01-1.08 0l-4.25-4.51a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 pb-2 pt-1">
                                <p class="text-sm font-semibold text-ink-800">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-ink-500">{{ auth()->user()->email }}</p>
                            </div>

                            <div class="px-2">
                                <x-dropdown-link :href="route('profile.edit')">
                                    Profile
                                </x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                                     onclick="event.preventDefault(); this.closest('form').submit();">
                                        Log Out
                                    </x-dropdown-link>
                                </form>
                            </div>
                        </x-slot>
                    </x-dropdown>
                @else
                    <div class="flex items-center gap-3">
                        <a href="{{ route('login') }}" class="nav-action-secondary">
                            Login
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="nav-action-primary">
                                Register
                            </a>
                        @endif
                    </div>
                @endauth
            </div>

            <div class="flex items-center xl:hidden">
                <button
                    @click="open = ! open"
                    class="inline-flex items-center justify-center rounded-2xl border border-sage-200 bg-white p-3 text-ink-600 shadow-sm transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 focus:outline-none"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }"
                              class="inline-flex"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{ 'hidden': !open, 'inline-flex': open }"
                              class="hidden"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              stroke-width="2"
                              d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="open" x-transition class="xl:hidden" style="display: none;">
        <div class="page-container pb-4">
            <div class="section-card p-3">
                <div class="space-y-2">
                    <x-responsive-nav-link :href="route('home')" :active="request()->routeIs('home')">
                        Home
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('books.index')" :active="request()->routeIs('books.*')">
                        Books
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('ai.book-assistant.index')" :active="request()->routeIs('ai.book-assistant.*')">
                        AI Book Assistant
                    </x-responsive-nav-link>

                    @auth
                        <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                            Notifications
                        </x-responsive-nav-link>

                        @if(auth()->user()->isAdmin())
                            <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                                Admin Dashboard
                            </x-responsive-nav-link>

                            <x-responsive-nav-link :href="route('admin.orders.index')" :active="request()->routeIs('admin.orders.*')">
                                Orders
                            </x-responsive-nav-link>

                            <x-responsive-nav-link :href="route('admin.data.index')" :active="request()->routeIs('admin.data.*')">
                                Data
                            </x-responsive-nav-link>

                            <x-responsive-nav-link :href="route('admin.audits.index')" :active="request()->routeIs('admin.audits.*')">
                                Audits
                            </x-responsive-nav-link>

                            <x-responsive-nav-link :href="route('admin.ai.index')" :active="request()->routeIs('admin.ai.*')">
                                AI Logs
                            </x-responsive-nav-link>

                            <x-responsive-nav-link :href="route('admin.ai-monitoring.index')" :active="request()->routeIs('admin.ai-monitoring.*')">
                                AI Monitoring
                            </x-responsive-nav-link>
                        @else
                            <x-responsive-nav-link :href="route('customer.dashboard')" :active="request()->routeIs('customer.*')">
                                Customer Dashboard
                            </x-responsive-nav-link>

                            <x-responsive-nav-link :href="route('cart.index')" :active="request()->routeIs('cart.*') || request()->routeIs('cart-items.*')">
                                Cart
                            </x-responsive-nav-link>

                            <x-responsive-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')">
                                Orders
                            </x-responsive-nav-link>
                        @endif
                    @endauth
                </div>

                @auth
                    <div class="mt-4 rounded-2xl bg-sage-50 p-4">
                        <div class="text-base font-semibold text-ink-900">{{ auth()->user()->name }}</div>
                        <div class="text-sm text-ink-500">{{ auth()->user()->email }}</div>
                        <div class="mt-2 text-xs font-medium text-brand-700">
                            Unread notifications: {{ auth()->user()->unreadNotifications()->count() }}
                        </div>

                        <div class="mt-4 space-y-2">
                            <x-responsive-nav-link :href="route('profile.edit')">
                                Profile
                            </x-responsive-nav-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-responsive-nav-link :href="route('logout')"
                                                       onclick="event.preventDefault(); this.closest('form').submit();">
                                    Log Out
                                </x-responsive-nav-link>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="mt-4 grid grid-cols-1 gap-2">
                        <a href="{{ route('login') }}" class="nav-action-secondary">
                            Login
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="nav-action-primary">
                                Register
                            </a>
                        @endif
                    </div>
                @endauth
            </div>
        </div>
    </div>
</nav>
