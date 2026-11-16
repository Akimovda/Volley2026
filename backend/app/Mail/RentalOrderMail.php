<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CourtBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Письма по заказу из виджета аренды. kind: received | confirmed | rejected | paid | cancelled (клиенту),
 * owner_new | owner_paid (арендодателю). Данные броней читаются при отправке — письмо всегда актуально.
 */
class RentalOrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $kind,
        public string $orderToken,
        public string $lang = 'ru',
        public ?string $reason = null,
    ) {
        $this->afterCommit();
        $this->locale($lang);
    }

    public function build(): static
    {
        $bookings = CourtBooking::where('order_token', $this->orderToken)
            ->with('court.direction.location')->orderBy('starts_at')->orderBy('court_id')->get();
        $first = $bookings->first();
        $location = $first?->court?->direction?->location;
        $isOwner = str_starts_with($this->kind, 'owner_');
        $statusUrl = route('rental_booking.status', ['token' => $this->orderToken, 'lang' => $this->lang]);

        return $this->subject(__('club.rm_subject_' . $this->kind, ['location' => $location?->name ?? '']))
            ->view('emails.rental_order', [
                'kind'      => $this->kind,
                'bookings'  => $bookings,
                'first'     => $first,
                'location'  => $location,
                'isOwner'   => $isOwner,
                'reason'    => $this->reason,
                'statusUrl' => $statusUrl,
                'panelUrl'  => route('club.bookings.index'),
                'total'     => $bookings->whereIn('status', CourtBooking::ACTIVE_STATUSES)->sum(fn ($b) => (float) $b->price_total)
                    ?: $bookings->sum(fn ($b) => (float) $b->price_total),
            ]);
    }
}
