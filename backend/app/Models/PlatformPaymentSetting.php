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
        'sub_fee_percent', 'sub_fee_mode', 'sub_tax_percent', 'sub_tax_mode', 'ad_fee_percent', 'ad_fee_mode', 'ad_tax_percent', 'ad_tax_mode', 'sub_fee_methods', 'ad_fee_methods', 'sub_tax_system', 'ad_tax_system', 'offer_choice', 'offer_methods',
    ];

    protected $casts = [
        'yoomoney_verified' => 'boolean',
        'yookassa_receipt_enabled' => 'boolean',
        'offer_choice' => 'boolean',
        'offer_methods' => 'array',
        'sub_fee_methods' => 'array',
        'ad_fee_methods' => 'array',
    ];

    /** Реально настроенные способы платформы. */
    public function availableMethods(): array
    {
        $m = [];
        if (!empty($this->tbank_link)) $m[] = 'tbank_link';
        if (!empty($this->sber_link)) $m[] = 'sber_link';
        if (!empty($this->yoomoney_shop_id) && !empty($this->yoomoney_secret_key)) $m[] = 'yoomoney';
        return $m;
    }

    /** Способы на выбор для Pro/Premium/рекламы (≥2), иначе [] — тогда действует `method`. */
    public function offeredMethods(): array
    {
        return \App\Support\PaymentMethods::offered((bool) $this->offer_choice, $this->offer_methods, $this->availableMethods());
    }

    /** Способ платежа платформы: выбранный игроком (если выбор включён и значение допустимо) либо основной. */
    public function resolveMethod(?string $chosen): string
    {
        $offered = $this->offeredMethods();
        return $offered && in_array($chosen, $offered, true) ? $chosen : $this->method;
    }
}
