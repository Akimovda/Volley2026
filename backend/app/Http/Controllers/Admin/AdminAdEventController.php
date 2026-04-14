<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\AdEventPaymentController;
use App\Models\Event;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminAdEventController extends Controller
{
    public function confirm(Request $request, Event $event)
    {
        if ($event->ad_payment_status === 'paid') {
            return redirect()->route('events.show', $event)
                ->with('success', '✅ Уже подтверждено ранее.');
        }

        if ($event->ad_payment_status !== 'pending') {
            return redirect()->route('events.show', $event)
                ->with('error', 'Мероприятие не ожидает подтверждения.');
        }

        $event->update([
            'ad_payment_status'     => 'paid',
            'ad_payment_expires_at' => null,
        ]);

        // Записываем транзакцию / обновляем существующую
        $organizer = User::find($event->organizer_id);
        if ($organizer) {
            $payment = \App\Models\Payment::where('event_id', $event->id)
                ->where('user_id', $organizer->id)
                ->latest()->first();

            if ($payment) {
                $payment->update([
                    'status'           => 'paid',
                    'org_confirmed'    => true,
                    'org_confirmed_at' => now(),
                ]);
            } else {
                AdEventPaymentController::recordTransaction($event, $organizer);
            }
        }

        // Уведомляем организатора
        $this->notifyOrganizer($event,
            '✅ Оплата подтверждена!',
            "Ваше рекламное мероприятие «{$event->title}» опубликовано и видно всем участникам."
        );

        return redirect()->route('events.show', $event)
            ->with('success', '✅ Оплата подтверждена, мероприятие опубликовано.');
    }

    public function reject(Request $request, Event $event)
    {
        // Уведомляем организатора до удаления
        $this->notifyOrganizer($event,
            '❌ Оплата не подтверждена',
            "Мероприятие «{$event->title}» было отклонено администратором и удалено."
        );

        $event->update(['ad_payment_status' => 'expired']);
        $event->delete();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Мероприятие отклонено и удалено.');
    }

    private function notifyOrganizer(Event $event, string $title, string $body): void
    {
        try {
            $organizer = User::find($event->organizer_id);
            if (!$organizer) return;

            app(UserNotificationService::class)->create(
                userId:   $organizer->id,
                type:     'ad_event_payment_result',
                title:    $title,
                body:     $body,
                payload:  [
                    'event_id'    => $event->id,
                    'button_text' => 'Открыть мероприятие',
                    'button_url'  => route('events.show', $event),
                ],
                channels: ['in_app', 'telegram', 'vk', 'max'],
            );
        } catch (\Throwable $e) {
            Log::warning('AdminAdEventController notify failed: ' . $e->getMessage());
        }
    }
}
