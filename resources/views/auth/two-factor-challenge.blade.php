<x-guest-layout>
    @if(session('error'))
        <div class="auth-error-box">
            {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div class="auth-success-box">
            {{ session('success') }}
        </div>
    @endif

    <div>
        <div class="auth-top-icon">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75 11.25 15 15 9.75m6 2.25A9 9 0 1112 3a9 9 0 019 9z" />
            </svg>
        </div>

        <h1 class="auth-page-title">Two-factor authentication</h1>
        <p class="auth-page-subtitle">
            Enter the 6-digit code sent to <span class="font-semibold text-ink-800">{{ $email }}</span>.
        </p>
    </div>

    <form method="POST" action="{{ route('twofactor.verify') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <x-input-label for="code" :value="'6-digit code'" />
            <input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                maxlength="6"
                class="auth-code-input"
                placeholder="123456"
                required
            >
            @error('code')
                <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-divider"></div>

        <div class="flex justify-end">
            <x-primary-button type="submit">
                Verify
            </x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('twofactor.resend') }}" class="mt-3">
        @csrf
        <x-secondary-button type="submit">
            Resend Code
        </x-secondary-button>
    </form>

    <div class="auth-note mt-5">
        Check the code in <code class="rounded bg-white px-2 py-1 text-xs text-ink-700">storage/logs/laravel.log</code>.
    </div>
</x-guest-layout>
