<x-guest-layout>
    <div>
        <div class="auth-top-icon">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15.75a2.25 2.25 0 002.25-2.25A2.25 2.25 0 0012 11.25a2.25 2.25 0 00-2.25 2.25A2.25 2.25 0 0012 15.75zm6-6.75V6.75a6 6 0 10-12 0V9m-1.5 0h15A1.5 1.5 0 0121 10.5v9A1.5 1.5 0 0119.5 21h-15A1.5 1.5 0 013 19.5v-9A1.5 1.5 0 014.5 9z" />
            </svg>
        </div>

        <h1 class="auth-page-title">Confirm your password</h1>
        <p class="auth-page-subtitle">
            This is a secure area. Please confirm your password before continuing.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="auth-divider"></div>

        <div class="flex justify-end">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
