<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\YookassaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class YookassaWebhookController extends Controller
{
    public function __construct(private YookassaService $yookassa) {}

    public function handle(Request $request): Response
    {
        $eventId = $this->yookassa->handleWebhook($request->getContent());

        if ($eventId === null) {
            return response('ok', 200);
        }

        $event = Event::find($eventId);

        if (!$event || $event->ad_payment_status === 'paid') {
            return response('ok', 200);
        }

        $event->update([
            'ad_payment_status'     => 'paid',
            'ad_payment_expires_at' => null,
        ]);

        Log::info('YookassaWebhook: ad event paid', ['event_id' => $eventId]);

        $this->notifyOrganizer($event);

        return response('ok', 200);
    }

    private function notifyOrganizer(Event $event): void
    {
        try {
            $user = $event->user;
            if (!$user) return;

            $text = "✅ Оплата мероприятия подтверждена!\n\n"
                . "🏐 *{$event->title}*\n"
                . "Мероприятие опубликовано и видно всем участникам.";

            if ($user->telegram_id) {
                app(\App\Services\TelegramBotService::class)
                    ->sendMessage($user->telegram_id, $text, ['parse_mode' => 'Markdown']);
            }
        } catch (\Throwable $e) {
            Log::error('YookassaWebhook: notify failed', ['error' => $e->getMessage()]);
        }
    }
}