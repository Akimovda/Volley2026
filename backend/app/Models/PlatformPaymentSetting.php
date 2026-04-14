<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformPaymentSetting extends Model
{
    protected $fillable = [
        'method',
        'tbank_link',
        'sber_link',
        'yoomoney_shop_id',
        'yoomoney_secret_key',
        'yoomoney_verified',
        'ad_event_price_rub',
        'payment_admin_id',
        'organizer_pro_trial_days',
        'organizer_pro_month_rub',
        'organizer_pro_quarter_rub',
        'organizer_pro_year_rub',
    ];

    protected $casts = [
        'yoomoney_verified' => 'boolean',
    ];
}
