<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-ink-900 leading-tight">
                Profile Settings
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                Manage your account details, address, password, two-factor authentication, and account safety.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'profile-updated')
            <div class="soft-alert-success">
                Profile updated successfully.
            </div>
        @endif

        @if (session('status') === 'password-updated')
            <div class="soft-alert-success">
                Password updated successfully.
            </div>
        @endif

        @if(session('success'))
            <div class="soft-alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="soft-alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <div class="profile-layout">
            <aside class="profile-sidebar-card">
                <div class="flex flex-col items-start gap-4">
                    @if($user->profile_image)
                        <img
                            src="{{ asset('storage/' . $user->profile_image) }}"
                            alt="{{ $user->name }}"
                            class="profile-avatar"
                        >
                    @else
                        <div class="flex h-24 w-24 items-center justify-center rounded-full bg-brand-100 text-3xl font-bold text-brand-700 ring-4 ring-white shadow-soft">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                    @endif

                    <div>
                        <h3 class="text-xl font-bold text-ink-900">{{ $user->name }}</h3>
                        <p class="mt-1 text-sm text-ink-500">{{ $user->email }}</p>
                        @if($user->phone)
                            <p class="mt-1 text-sm text-ink-500">{{ $user->phone }}</p>
                        @endif
                    </div>

                    <div class="w-full rounded-2xl bg-sage-50 p-4">
                        <p class="text-sm font-semibold text-ink-800">Account Status</p>
                        <p class="mt-2 text-sm text-ink-600">
                            Email:
                            @if($user->hasVerifiedEmail())
                                <span class="font-semibold text-emerald-700">Verified</span>
                            @else
                                <span class="font-semibold text-amber-700">Not verified</span>
                            @endif
                        </p>
                        <p class="mt-2 text-sm text-ink-600">
                            Two-Factor:
                            <span class="font-semibold {{ auth()->user()->two_factor_enabled ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ auth()->user()->two_factor_enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </p>
                    </div>

                    <div class="w-full rounded-2xl border border-sage-100 bg-white p-4">
                        <p class="text-sm font-semibold text-ink-800">Default Address</p>
                        <div class="mt-2 text-sm leading-6 text-ink-500">
                            @if(
                                $user->default_address_line_1 ||
                                $user->default_address_line_2 ||
                                $user->default_city ||
                                $user->default_province ||
                                $user->default_postal_code ||
                                $user->default_country
                            )
                                <p>{{ $user->default_address_line_1 }}</p>
                                @if($user->default_address_line_2)
                                    <p>{{ $user->default_address_line_2 }}</p>
                                @endif
                                <p>
                                    {{ $user->default_city }}{{ $user->default_city && $user->default_province ? ',' : '' }}
                                    {{ $user->default_province }}
                                </p>
                                <p>
                                    {{ $user->default_postal_code }}{{ $user->default_postal_code && $user->default_country ? ',' : '' }}
                                    {{ $user->default_country }}
                                </p>
                            @else
                                <p>No default address set yet.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </aside>

            <div class="profile-main-stack">
                <section class="profile-section">
                    <header>
                        <h2 class="profile-section-title">Profile Information</h2>
                        <p class="profile-section-text">
                            Update your account details, contact number, profile image, and default address.
                        </p>
                    </header>

                    <form method="POST"
                          action="{{ route('profile.update') }}"
                          enctype="multipart/form-data"
                          class="mt-6 space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <x-input-label for="profile_image" value="Profile Image" />

                            @if($user->profile_image)
                                <div class="mt-3 mb-3">
                                    <img src="{{ asset('storage/' . $user->profile_image) }}"
                                         alt="{{ $user->name }}"
                                         class="h-20 w-20 rounded-full object-cover ring-4 ring-white shadow-soft">
                                </div>
                            @endif

                            <input id="profile_image"
                                   name="profile_image"
                                   type="file"
                                   accept="image/*"
                                   class="form-input mt-3">

                            <x-input-error :messages="$errors->get('profile_image')" />
                        </div>

                        <div class="profile-grid-2">
                            <div>
                                <x-input-label for="name" value="Name" />
                                <x-text-input id="name"
                                              name="name"
                                              type="text"
                                              :value="old('name', $user->name)"
                                              required />
                                <x-input-error :messages="$errors->get('name')" />
                            </div>

                            <div>
                                <x-input-label for="email" value="Email" />
                                <x-text-input id="email"
                                              name="email"
                                              type="email"
                                              :value="old('email', $user->email)"
                                              required />
                                <x-input-error :messages="$errors->get('email')" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input id="phone"
                                          name="phone"
                                          type="text"
                                          :value="old('phone', $user->phone)" />
                            <x-input-error :messages="$errors->get('phone')" />
                        </div>

                        <div class="border-t border-sage-100 pt-6">
                            <h3 class="text-lg font-semibold text-ink-900">Default Address</h3>
                            <p class="mt-1 text-sm text-ink-500">
                                Save your default delivery address for faster checkout.
                            </p>

                            <div class="mt-5 space-y-4">
                                <div>
                                    <x-input-label for="default_address_line_1" value="Address Line 1" />
                                    <x-text-input id="default_address_line_1"
                                                  name="default_address_line_1"
                                                  type="text"
                                                  :value="old('default_address_line_1', $user->default_address_line_1)" />
                                    <x-input-error :messages="$errors->get('default_address_line_1')" />
                                </div>

                                <div>
                                    <x-input-label for="default_address_line_2" value="Address Line 2" />
                                    <x-text-input id="default_address_line_2"
                                                  name="default_address_line_2"
                                                  type="text"
                                                  :value="old('default_address_line_2', $user->default_address_line_2)" />
                                    <x-input-error :messages="$errors->get('default_address_line_2')" />
                                </div>

                                <div class="profile-grid-compact">
                                    <div>
                                        <x-input-label for="default_city" value="City" />
                                        <x-text-input id="default_city"
                                                      name="default_city"
                                                      type="text"
                                                      :value="old('default_city', $user->default_city)" />
                                        <x-input-error :messages="$errors->get('default_city')" />
                                    </div>

                                    <div>
                                        <x-input-label for="default_province" value="Province" />
                                        <x-text-input id="default_province"
                                                      name="default_province"
                                                      type="text"
                                                      :value="old('default_province', $user->default_province)" />
                                        <x-input-error :messages="$errors->get('default_province')" />
                                    </div>
                                </div>

                                <div class="profile-grid-compact">
                                    <div>
                                        <x-input-label for="default_postal_code" value="Postal Code" />
                                        <x-text-input id="default_postal_code"
                                                      name="default_postal_code"
                                                      type="text"
                                                      :value="old('default_postal_code', $user->default_postal_code)" />
                                        <x-input-error :messages="$errors->get('default_postal_code')" />
                                    </div>

                                    <div>
                                        <x-input-label for="default_country" value="Country" />
                                        <x-text-input id="default_country"
                                                      name="default_country"
                                                      type="text"
                                                      :value="old('default_country', $user->default_country)" />
                                        <x-input-error :messages="$errors->get('default_country')" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-actions">
                            <x-primary-button>
                                Save Profile
                            </x-primary-button>

                            <a href="{{ route('dashboard') }}" class="nav-action-secondary">
                                Back
                            </a>
                        </div>
                    </form>
                </section>

                <section class="profile-section">
                    <header>
                        <h2 class="profile-section-title">Change Password</h2>
                        <p class="profile-section-text">
                            Update your password regularly to keep your account secure.
                        </p>
                    </header>

                    <form method="POST" action="{{ route('profile.password.update') }}" class="mt-6 space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="current_password" value="Current Password" />
                            <x-text-input id="current_password"
                                          name="current_password"
                                          type="password" />
                            <x-input-error :messages="$errors->get('current_password')" />
                        </div>

                        <div>
                            <x-input-label for="new_password" value="New Password" />
                            <x-text-input id="new_password"
                                          name="new_password"
                                          type="password" />
                            <x-input-error :messages="$errors->get('new_password')" />
                        </div>

                        <div>
                            <x-input-label for="new_password_confirmation" value="Confirm New Password" />
                            <x-text-input id="new_password_confirmation"
                                          name="new_password_confirmation"
                                          type="password" />
                        </div>

                        <div class="profile-actions">
                            <x-primary-button>
                                Update Password
                            </x-primary-button>
                        </div>
                    </form>
                </section>

                <section class="profile-section">
                    <header>
                        <h2 class="profile-section-title">Two-Factor Authentication (2FA)</h2>
                        <p class="profile-section-text">
                            Add another layer of security using email OTP verification.
                        </p>
                    </header>

                    <div class="mt-6 space-y-4">
                        <div class="soft-alert-info">
                            Status:
                            <strong>{{ auth()->user()->two_factor_enabled ? 'Enabled' : 'Disabled' }}</strong>
                        </div>

                        @if(!auth()->user()->two_factor_enabled)
                            <form method="POST" action="{{ route('twofactor.enable') }}">
                                @csrf
                                <x-primary-button type="submit">
                                    Enable 2FA (Email OTP)
                                </x-primary-button>
                            </form>

                            <p class="text-xs text-ink-500">
                                Email must be verified to enable 2FA.
                            </p>
                        @else
                            <form method="POST" action="{{ route('twofactor.disable') }}">
                                @csrf
                                <x-danger-button type="submit" onclick="return confirm('Disable 2FA?')">
                                    Disable 2FA
                                </x-danger-button>
                            </form>
                        @endif
                    </div>
                </section>

                <section class="profile-section">
                    <header>
                        <h2 class="profile-section-title text-red-700">Delete Account</h2>
                        <p class="profile-section-text">
                            Enter your password to permanently delete your account.
                        </p>
                    </header>

                    <form method="POST" action="{{ route('profile.destroy') }}" class="mt-6 space-y-4">
                        @csrf
                        @method('DELETE')

                        <div>
                            <x-input-label for="password" value="Password" />
                            <x-text-input id="password"
                                          name="password"
                                          type="password" />
                            <x-input-error :messages="$errors->userDeletion->get('password')" />
                        </div>

                        <div class="profile-actions">
                            <x-danger-button type="submit" onclick="return confirm('Delete your account?')">
                                Delete Account
                            </x-danger-button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
