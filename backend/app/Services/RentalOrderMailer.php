<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\RentalOrderMail;
use App\Models\CourtBooking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Рассылка писем по заказам из виджета. Сбой почты никогда не ломает бронирование (пишем в лог). */
class RentalOrderMailer
{
    public function toClient(string $kind, string $orderToken, ?string $reason = null): void
    {
        $this->send($kind, $orderToken, false, $reason);
    }

    public function toOwner(string $kind, string $orderToken): void
    {
        $this->send($kind, $orderToken, true, null);
    }

    private function send(string $kind, string $orderToken, bool $owner, ?string $reason): void
    {
        try {
            $first = CourtBooking::where('order_token', $orderToken)->with('court.direction.location.owner')->orderBy('id')->first();
            if (!$first) {
                return;
            }
            $to = $owner ? $first->court?->direction?->location?->owner?->email : $first->guest_email;
            if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $lang = $owner ? 'ru' : ($first->guest_locale ?: 'ru');
            Mail::to($to)->queue(new RentalOrderMail($kind, $orderToken, $lang, $reason));
        } catch (\Throwable $e) {
            report($e);
            Log::warning('[RentalOrderMailer] письмо не поставлено в очередь', ['kind' => $kind, 'error' => $e->getMessage()]);
        }
    }
}
