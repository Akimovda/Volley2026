<x-voll-layout body_class="trainer-profile-edit-page">

    <x-slot name="title">{{ __('trainers.profile_edit_title') }}</x-slot>
    <x-slot name="h1">{{ __('trainers.profile_edit_h1') }}</x-slot>

    <div class="container">

        @if(isset($pendingMemberships) && $pendingMemberships->isNotEmpty())
        <div class="ramka">
            <h2 class="-mt-05">{{ __('trainers.pending_invites_title') }}</h2>
            @foreach($pendingMemberships as $membership)
            <div class="card mb-1" style="height:auto;">
                <div class="d-flex fvc between" style="flex-wrap:wrap;gap:8px;">
                    <div class="b-600">{{ $membership->school?->name ?? ('#' . $membership->school_id) }}</div>
                    <div class="d-flex" style="gap:8px;">
                        <form method="POST" action="{{ route('trainer.memberships.confirm', $membership) }}">
                            @csrf
                            <button type="submit" class="btn btn-small">{{ __('trainers.membership_confirm_btn') }}</button>
                        </form>
                        <form method="POST" action="{{ route('trainer.memberships.decline', $membership) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-small">{{ __('trainers.membership_decline_btn') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <div class="ramka">

            <form method="POST" action="{{ route('trainer.profile.update') }}" class="form">
                @csrf

                <div class="mb-2">
                    <label class="f-15 mb-05">{{ __('trainers.field_specialization') }}</label>
                    <input type="text" name="specialization" maxlength="255"
                           value="{{ old('specialization', $profile->specialization ?? '') }}">
                </div>

                <div class="mb-2">
                    <label class="f-15 mb-05">{{ __('trainers.field_bio') }}</label>
                    <textarea name="bio" rows="5" maxlength="5000">{{ old('bio', $profile->bio ?? '') }}</textarea>
                </div>

                <div class="mb-2">
                    <label class="f-15 mb-05">{{ __('trainers.field_experience_years') }}</label>
                    <input type="number" name="experience_years" min="0" max="80"
                           value="{{ old('experience_years', $profile->experience_years ?? '') }}">
                </div>

                <div class="mb-2">
                    <label class="checkbox-item">
                        <input type="checkbox" name="is_public" value="1"
                               {{ old('is_public', $profile->is_public ?? true) ? 'checked' : '' }}>
                        <div class="custom-checkbox"></div>
                        <span>{{ __('trainers.field_is_public') }}</span>
                    </label>
                </div>

                <button class="btn" type="submit">{{ __('trainers.btn_save') }}</button>

                @if(session('status'))
                <div class="mt-2 f-16" style="color:#4caf50;">{{ session('status') }}</div>
                @endif

            </form>

        </div>
    </div>

</x-voll-layout>
