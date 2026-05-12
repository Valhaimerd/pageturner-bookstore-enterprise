<x-guest-layout>
    <div>
        <div class="auth-top-icon">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 9V5.25A3.75 3.75 0 0012 1.5A3.75 3.75 0 008.25 5.25V9m-1.5 0h10.5A2.25 2.25 0 0119.5 11.25v8.25A2.25 2.25 0 0117.25 21.75H6.75A2.25 2.25 0 014.5 19.5v-8.25A2.25 2.25 0 016.75 9z" />
            </svg>
        </div>

        <h1 class="auth-page-title">Welcome back</h1>
        <p class="auth-page-subtitle">
            Log in to continue browsing books, managing your cart, and checking your orders.
        </p>
    </div>

    <x-auth-session-status class="auth-success-box" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="auth-field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="auth-checkbox" name="remember">
                <span class="auth-check-label">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="auth-inline-link" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <div class="auth-divider"></div>

        <div class="auth-actions">
            @if (Route::has('register'))
                <a class="auth-inline-link" href="{{ route('register') }}">
                    {{ __('Need an account? Register') }}
                </a>
            @else
                <span></span>
            @endif

            <x-primary-button>
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
