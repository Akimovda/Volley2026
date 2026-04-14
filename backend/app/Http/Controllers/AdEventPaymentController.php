<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdEventPaymentController extends Controller
{
    public function notify(Request $request, Event $event)
    {
        $user = $request->user();

        if (!$user || (int)$user->id !== (int)$event->organizer_id) {
            abort(403);
        }

        if ($event->ad_payment_status !== 'pending') {
            return back()->with('error', 'Мероприятие не ожидает оплаты.');
        }

        // Сохраняем флаг и транзакцию ДО отправки уведомлений
        $event->update(['ad_organizer_notified' => true]);
        self::recordTransaction($event, $user);

        // Отправляем уведомления (ошибки не блокируют основной флоу)
        try {
            $admins  = User::where('role', 'admin')->get();
            $service = app(UserNotificationService::class);

            foreach ($admins as $admin) {
                $service->createAdPaymentPendingNotification($admin, $event, $user);
            }
        } catch (\Throwable $e) {
            Log::warning('AdEventPaymentController notify failed: ' . $e->getMessage());
        }

        return back()->with('success', '✅ Администратор уведомлён. Ожидайте подтверждения.');
    }

    public static function recordTransaction(Event $event, User $organizer): void
    {
        try {
            $platMethod = \App\Models\PlatformPaymentSetting::first()?->method ?? 'yoomoney';

            \App\Models\Payment::create([
                'user_id'            => $organizer->id,
                'organizer_id'       => $organizer->id,
                'event_id'           => $event->id,
                'method'             => $platMethod,
                'status'             => 'pending',
                'amount_minor'       => (int)$event->ad_price_rub * 100,
                'currency'           => 'RUB',
                'org_confirmed'      => false,
                'user_confirmed'     => true,
                'user_confirmed_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AdEventPaymentController::recordTransaction failed', ['error' => $e->getMessage()]);
        }
    }
}
