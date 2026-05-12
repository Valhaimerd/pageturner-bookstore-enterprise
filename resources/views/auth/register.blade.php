<x-guest-layout>
    <div>
        <div class="auth-top-icon">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 7.5V6a3 3 0 10-6 0v1.5m-4.5 0h9A2.25 2.25 0 0118.75 9.75v8.25A2.25 2.25 0 0116.5 20.25h-9a2.25 2.25 0 01-2.25-2.25V9.75A2.25 2.25 0 017.5 7.5zm7.5-1.5a3 3 0 10-6 0v1.5h6V6z" />
            </svg>
        </div>

        <h1 class="auth-page-title">Create your account</h1>
        <p class="auth-page-subtitle">
            Register to buy books, manage your profile, and track your orders in one place.
        </p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <x-input-label for="name" :value="__('Full Name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="auth-field">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="auth-field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="auth-field">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="auth-note">
            Use an email you can access because verification and password reset links will be sent there.
        </div>

        <div class="auth-divider"></div>

        <div class="auth-actions">
            <a class="auth-inline-link" href="{{ route('login') }}">
                {{ __('Already registered? Log in') }}
            </a>

            <x-primary-button>
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
