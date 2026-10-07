<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    protected $table = 'event_registrations';

    protected $fillable = [
        'event_id',
        'user_id',
        'occurrence_id',
        'position',
        'status',
        'cancelled_at',
        'confirmed_at',
    ];

    protected $casts = [
        'cancelled_at'      => 'datetime',
        'confirmed_at'      => 'datetime',
        'is_cancelled'      => 'boolean',
        'auto_booked'       => 'boolean',
        'payment_expires_at' => 'datetime',
        'premium_auto_confirm_deadline_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function premiumAutoBooking()
    {
        return $this->belongsTo(PremiumAutoBooking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function occurrence()
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /**
     * Маркеры авто-записи (Premium/абонемент/купон) от ПРЕДЫДУЩЕЙ жизни строки. Любой путь, который
     * реактивирует старую отменённую регистрацию, обязан их сбросить — иначе просроченный
     * premium_auto_confirm_deadline_at (или subscription+confirmed_at=null) тут же попадёт под
     * premium:expire-auto-bookings / AutoUnconfirmBookingJob и выпишет игрока, записанного уже по-другому
     * (из листа ожидания, организатором, самостоятельно). Для DB::table()->update() — массив как есть,
     * для Eloquent — forceFill(). auto_booked после сброса выставляет сам вызывающий, если нужно true.
     */
    public static function autoBookingResetAttributes(): array
    {
        return [
            'premium_auto_booking_id'          => null,
            'premium_auto_confirm_deadline_at' => null,
            'confirmed_at'                     => null,
            'subscription_id'                  => null,
            'subscription_usage_id'            => null,
            'auto_booked'                      => false,
            'coupon_id'                        => null,
            'coupon_discount_pct'              => null,
        ];
    }
}
