<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    {{-- Success message after auto-registration --}}
    @if (session('status'))
        <div class="v-container mt-6">
            <div class="v-alert v-alert--success">
                <div class="v-alert__text">
                    {{ session('status') }}
                </div>
            </div>
        </div>
    @endif

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">

            {{-- Base Jetstream profile information --}}
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')
                <x-section-border />
            @endif

            {{-- EXTRA PROFILE (анкета игрока) --}}
            @include('profile.extra-profile-form')

            {{-- Password --}}
            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div>
                    @livewire('profile.update-password-form')
                </div>
                <x-section-border />
            @endif

            {{-- Two-factor authentication --}}
            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <div>
                    @livewire('profile.two-factor-authentication-form')
                </div>
                <x-section-border />
            @endif

            {{-- Logout other sessions --}}
            <div>
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            {{-- Delete account --}}
            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <x-section-border />
                <div>
                    @livewire('profile.delete-user-form')
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
