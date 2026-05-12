<x-guest-layout>
    <div>
        <div class="auth-top-icon">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 9h.01M9 9h.01M8.25 15A4.5 4.5 0 0012 17.25A4.5 4.5 0 0015.75 15m2.55-9A9 9 0 113.2 9.2A9 9 0 0118.3 6z" />
            </svg>
        </div>

        <h1 class="auth-page-title">Forgot your password?</h1>
        <p class="auth-page-subtitle">
            Enter your email address and a password reset link will be sent to you.
        </p>
    </div>

    <x-auth-session-status class="auth-success-box" :status="session('status')" />

    @if (session('warning'))
        <div class="soft-alert-warning">
            {{ session('warning') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="auth-divider"></div>

        <div class="auth-actions">
            <a href="{{ route('login') }}" class="auth-inline-link">
                Back to login
            </a>

            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
