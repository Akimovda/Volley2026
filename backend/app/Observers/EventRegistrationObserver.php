<?php

namespace App\Observers;

use App\Models\EventRegistration;
use App\Services\WaitlistService;

class EventRegistrationObserver
{
    public function created(EventRegistration $registration): void
    {
        // Удаляем из резерва если записался
        if ($registration->occurrence_id && $registration->user_id) {
            app(WaitlistService::class)->removeIfRegistered(
                (int) $registration->occurrence_id,
                (int) $registration->user_id
            );
        }
    }

    public function deleted(EventRegistration $registration): void
    {
        $this->triggerWaitlist($registration);
    }

    public function updated(EventRegistration $registration): void
    {
        $cancelledByStatus    = $registration->wasChanged('status') && $registration->status === 'cancelled';
        $cancelledByIsFlag    = $registration->wasChanged('is_cancelled') && $registration->is_cancelled;
        $cancelledByCancelledAt = $registration->wasChanged('cancelled_at') && $registration->cancelled_at !== null;

        if ($cancelledByStatus || $cancelledByIsFlag || $cancelledByCancelledAt) {
            $this->triggerWaitlist($registration);
        }
    }

    private function triggerWaitlist(EventRegistration $registration): void
    {
        if (!$registration->occurrence_id) return;

        $occurrence = $registration->occurrence;
        if (!$occurrence) return;

        $position = (string)($registration->position ?? '');

        app(WaitlistService::class)->onSpotFreed($occurrence, $position);
    }
}
