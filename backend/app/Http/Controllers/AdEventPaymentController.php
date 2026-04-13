<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdEventPaymentController extends Controller
{
    public function notify(Request $request, Event $event)
    {
        $user = $request->user();

        // Только организатор может уведомить
        if (!$user || (int)$user->id !== (int)$event->organizer_id) {
            abort(403);
        }

        if ($event->ad_payment_status !== 'pending') {
            return back()->with('error', 'Мероприятие не ожидает оплаты.');
        }

        // Уведомляем администратора
        try {
            $admins = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->get();
            $eventUrl = route('events.show', $event);
            $msg = "💰 Организатор сообщает об оплате рекламного мероприятия!\n\n"
                 . "📌 «{$event->title}»\n"
                 . "👤 {$user->last_name} {$user->first_name}\n"
                 . "💵 {$event->ad_price_rub} ₽\n"
                 . "🔗 {$eventUrl}\n\n"
                 . "Подтвердите оплату в панели администратора.";

            $buttons = [
                [['text' => '✅ Подтвердить', 'url' => route('admin.events.ad.confirm', $event)]],
                [['text' => '❌ Отклонить',   'url' => route('admin.events.ad.reject', $event)]],
            ];

            foreach ($admins as $admin) {
                app(\App\Services\NotificationDeliverySender::class)
                    ->sendToUser($admin, $msg, $buttons);
            }
        } catch (\Throwable $e) {
            Log::warning('AdEventPaymentController notify failed: ' . $e->getMessage());
        }

        return back()->with('success', '✅ Администратор уведомлён. Ожидайте подтверждения.');
    }
}
