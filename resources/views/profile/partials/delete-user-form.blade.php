<section class="space-y-6">
    <header>
        <h2 class="profile-section-title text-red-700">
            {{ __('Delete Account') }}
        </h2>

        <p class="profile-section-text">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. This action cannot be undone.') }}
        </p>
    </header>

    <div class="soft-alert-danger">
        Please make sure you really want to remove this account before continuing.
    </div>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >
        {{ __('Delete Account') }}
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-8">
            @csrf
            @method('delete')

            <h2 class="text-xl font-semibold tracking-tight text-ink-900">
                {{ __('Delete your account?') }}
            </h2>

            <p class="mt-2 text-sm leading-6 text-ink-500">
                {{ __('Once your account is deleted, all associated resources and data will be permanently removed. Enter your password to confirm.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" :value="__('Password')" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" />
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
