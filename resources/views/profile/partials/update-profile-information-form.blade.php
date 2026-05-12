<section>
    <header>
        <h2 class="profile-section-title">
            {{ __('Profile Information') }}
        </h2>

        <p class="profile-section-text">
            {{ __("Update your account details, email address, and profile image.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="profile_image" :value="__('Profile Image')" />

            @if($user->profile_image)
                <div class="mt-3">
                    <img
                        src="{{ asset('storage/' . $user->profile_image) }}"
                        alt="{{ $user->name }}"
                        class="h-20 w-20 rounded-full object-cover ring-4 ring-white shadow-soft"
                    >
                </div>
            @endif

            <input
                id="profile_image"
                name="profile_image"
                type="file"
                accept="image/*"
                class="form-input mt-3"
            />

            <x-input-error :messages="$errors->get('profile_image')" />
        </div>

        <div class="profile-grid-2">
            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>
        </div>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="soft-alert-warning">
                <p>
                    {{ __('Your email address is unverified.') }}
                    <button form="send-verification" class="ml-1 font-semibold underline">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 font-medium text-emerald-700">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif

                @if (session('warning'))
                    <p class="mt-2 font-medium text-amber-700">
                        {{ session('warning') }}
                    </p>
                @endif
            </div>
        @endif

        <div class="profile-actions">
            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="inline-fade-status"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
