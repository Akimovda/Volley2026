<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventRegistrationInviteController extends Controller
{
    public function __construct(
        private UserNotificationService $notificationService
    ) {}

    public function store(Request $request, Event $event): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'to_user_ids'   => ['required', 'array', 'min:1', 'max:20'],
            'to_user_ids.*' => ['integer', 'min:1'],
            'occurrence_id' => ['required', 'integer', 'min:1'],
        ]);

        $occurrenceId = (int) $data['occurrence_id'];
        $toUserIds    = array_unique(array_map('intval', $data['to_user_ids']));

        $occurrence = EventOccurrence::query()
            ->where('id', $occurrenceId)
            ->where('event_id', (int) $event->id)
            ->first();

        if (!$occurrence) {
            return back()->with('error', 'Occurrence не найден.');
        }

        // Уже записанные на это occurrence
        $registeredUserIds = DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->where(function ($q) {
                $q->whereNull('cancelled_at')
                  ->where(function ($q2) {
                      $q2->whereNull('status')->orWhere('status', 'confirmed');
                  });
            })
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $eventUrl = route('events.show', [
            'event'      => (int) $event->id,
            'occurrence' => $occurrenceId,
        ]);

        if (!empty($event->is_private) && !empty($event->public_token)) {
            $eventUrl = route('events.public', ['token' => $event->public_token]);
        }

        $sent    = 0;
        $skipped = [];
        $errors  = [];

        foreach ($toUserIds as $toUserId) {
            if ($toUserId === (int) $user->id) {
                $skipped[] = '😉 Вы уже записаны!';
                continue;
            }

            if (in_array($toUserId, $registeredUserIds, true)) {
                $toUser = User::query()->find($toUserId);
                $name   = $toUser?->name ?: ('#' . $toUserId);
                $skipped[] = "😉 {$name} уже записан на это мероприятие!";
                continue;
            }

            $toUser = User::query()->find($toUserId);
            if (!$toUser) {
                continue;
            }

            try {
                $this->notificationService->createEventInviteNotification(
                    toUserId:     $toUserId,
                    fromUserId:   (int) $user->id,
                    eventId:      (int) $event->id,
                    occurrenceId: $occurrenceId,
                    eventTitle:   (string) $event->title,
                    eventUrl:     $eventUrl,
                );
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $errors[] = $toUser->name . ': ' . $e->getMessage();
            }
        }

        $parts = [];

        if ($sent > 0) {
            $parts[] = "✅ Приглашения отправлены: {$sent} игрок(ам).";
        }

        if (!empty($skipped)) {
            $parts = array_merge($parts, $skipped);
        }

        if (!empty($errors)) {
            $parts[] = 'Ошибки: ' . implode('; ', $errors);
        }

        if ($sent === 0 && empty($skipped)) {
            return back()->with('error', 'Не удалось отправить ни одного приглашения.');
        }

        return back()->with('status', implode(' ', $parts));
    }
}