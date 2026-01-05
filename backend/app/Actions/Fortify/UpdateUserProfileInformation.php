<?php

namespace App\Actions\Fortify;

use App\Models\Event;
use App\Models\User;
use App\Services\EventRegistrationRequirements;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if (
            $input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail
        ) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name'  => $input['name'],
                'email' => $input['email'],
            ])->save();
        }

        // === Автозапись на мероприятие после сохранения профиля ===
        $this->autoJoinPendingEventIfPossible($user);
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name'              => $input['name'],
            'email'             => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        // === Автозапись на мероприятие после сохранения профиля ===
        $this->autoJoinPendingEventIfPossible($user);
    }

    /**
     * Try to register user to the event they attempted to join before profile completion.
     */
    protected function autoJoinPendingEventIfPossible(User $user): void
    {
        $eventId = session('pending_event_join');

        if (empty($eventId)) {
            return;
        }

        $event = Event::find((int) $eventId);

        if (!$event) {
            session()->forget('pending_event_join');
            return;
        }

        /** @var EventRegistrationRequirements $requirements */
        $requirements = app(EventRegistrationRequirements::class);

        $missing = $requirements->missing($user, $event);

        // If still missing something, keep list for UI highlight
        if (!empty($missing)) {
            session()->put('pending_profile_required', $missing);
            return;
        }

        // Hard restrictions (levels too low etc.)
        $requirements->ensureEligible($user, $event);

        // Register (idempotent)
        DB::table('event_registrations')->updateOrInsert(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        session()->forget('pending_event_join');
        session()->forget('pending_profile_required');

        session()->flash(
            'status',
            'Вы успешно записаны на мероприятие, не забудьте взять с собой хорошее настроение.'
        );
    }
}
