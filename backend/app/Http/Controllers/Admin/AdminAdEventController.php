<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminAdEventController extends Controller
{
    public function confirm(Request $request, Event $event)
    {
        if ($event->ad_payment_status !== 'pending') {
            return back()->with('error', 'Мероприятие не ожидает подтверждения.');
        }

        $event->ad_payment_status = 'paid';
        $event->save();

        // Уведомляем организатора
        $this->notifyOrganizer($event,
            "✅ Оплата рекламного мероприятия «{$event->title}» подтверждена! Мероприятие опубликовано."
        );

        return back()->with('success', '✅ Оплата подтверждена, мероприятие опубликовано.');
    }

    public function reject(Request $request, Event $event)
    {
        $event->ad_payment_status = 'expired';
        $event->save();

        // Уведомляем организатора
        $this->notifyOrganizer($event,
            "❌ Оплата рекламного мероприятия «{$event->title}» не подтверждена. Мероприятие удалено."
        );

        $event->delete();

        return back()->with('success', 'Мероприятие отклонено и удалено.');
    }

    private function notifyOrganizer(Event $event, string $msg): void
    {
        try {
            $organizer = User::find($event->organizer_id);
            if ($organizer) {
                app(\App\Services\NotificationDeliverySender::class)
                    ->sendToUser($organizer, $msg, []);
            }
        } catch (\Throwable $e) {
            Log::warning('AdminAdEventController notify failed: ' . $e->getMessage());
        }
    }
}
