<x-guest-layout>
    <div>
        <div class="auth-top-icon">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21.75 8.25v7.5A2.25 2.25 0 0119.5 18H4.5a2.25 2.25 0 01-2.25-2.25v-7.5M21.75 8.25L12 13.5 2.25 8.25M21.75 8.25L19.5 6h-15l-2.25 2.25" />
            </svg>
        </div>

        <h1 class="auth-page-title">Verify your email</h1>
        <p class="auth-page-subtitle">
            Before continuing, please verify your email address using the link sent to your inbox.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="auth-success-box">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="soft-alert-warning">
            {{ session('warning') }}
        </div>
    @endif

    <div class="auth-note">
        {{ __('If you did not receive the email, you can request another verification link below.') }}
    </div>

    <div class="auth-divider"></div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="auth-muted-link-button">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
