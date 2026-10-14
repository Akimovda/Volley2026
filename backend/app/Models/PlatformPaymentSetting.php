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
        'organizer_pro_half_rub',
        'organizer_pro_year_rub',
        'premium_half_rub',
        'premium_year_rub',
        'yookassa_receipt_enabled',
        'yookassa_vat_code',
        'yookassa_tax_system_code',
        'sub_fee_percent', 'sub_fee_mode', 'sub_tax_percent', 'sub_tax_mode', 'ad_fee_percent', 'ad_fee_mode', 'ad_tax_percent', 'ad_tax_mode', 'sub_fee_methods', 'ad_fee_methods', 'sub_tax_system', 'ad_tax_system',
    ];

    protected $casts = [
        'yoomoney_verified' => 'boolean',
        'yookassa_receipt_enabled' => 'boolean',
        'sub_fee_methods' => 'array',
        'ad_fee_methods' => 'array',
    ];
}
