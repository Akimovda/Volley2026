<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;

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
            'to_user_id'    => ['required', 'integer', 'min:1'],
            'occurrence_id' => ['required', 'integer', 'min:1'],
        ]);

        $toUserId    = (int) $data['to_user_id'];
        $occurrenceId = (int) $data['occurrence_id'];

        if ($toUserId === (int) $user->id) {
            return back()->with('error', 'Нельзя пригласить самого себя.');
        }

        $toUser = User::query()->find($toUserId);
        if (!$toUser) {
            return back()->with('error', 'Пользователь не найден.');
        }

        $occurrence = EventOccurrence::query()
            ->where('id', $occurrenceId)
            ->where('event_id', (int) $event->id)
            ->first();

        if (!$occurrence) {
            return back()->with('error', 'Occurrence не найден.');
        }

        $eventUrl = route('events.show', [
            'event'      => (int) $event->id,
            'occurrence' => $occurrenceId,
        ]);

        // Если приватное — даём приватную ссылку
        if (!empty($event->is_private) && !empty($event->public_token)) {
            $eventUrl = route('events.public', ['token' => $event->public_token]);
        }

        try {
            $this->notificationService->createEventInviteNotification(
                toUserId:    $toUserId,
                fromUserId:  (int) $user->id,
                eventId:     (int) $event->id,
                occurrenceId: $occurrenceId,
                eventTitle:  (string) $event->title,
                eventUrl:    $eventUrl,
            );

            return back()->with('status', "Приглашение отправлено игроку {$toUser->name}.");
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Не удалось отправить приглашение: ' . $e->getMessage());
        }
    }
}
