<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only лог "инцидент задержки оплаты наличными" — одна запись на каждый
 * реальный факт постановки платежа в статус "не оплатил" (вручную организатором
 * через CashPaymentTrackingService::save() или автоматически через
 * ProcessUnattendedCashPayments). Не обновляется и не удаляется даже если платёж
 * потом всё-таки оплачен — источник данных для дашборда "Часто задерживают
 * оплату" (история должна сохраняться независимо от итогового статуса).
 */
class PaymentLateMark extends Model
{
    protected $fillable = [
        'payment_id',
        'user_id',
        'organizer_id',
        'event_id',
        'marked_at',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
