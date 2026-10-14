<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = [
        'organizer_id', 'default_method',
        'tbank_link', 'sber_link',
        'yoomoney_shop_id', 'yoomoney_secret_key',
        'yoomoney_enabled', 'yoomoney_verified',
        'refund_hours_full', 'refund_hours_partial', 'refund_partial_pct',
        'refund_no_quorum_full', 'payment_hold_minutes',
        'payment_for_rentals',
        'fee_percent', 'fee_mode', 'tax_percent', 'tax_mode', 'fee_methods', 'tax_system', 'yookassa_receipt_enabled', 'yookassa_vat_code', 'yookassa_tax_system_code',
    ];

    protected $casts = [
        'yoomoney_enabled'    => 'boolean',
        'yoomoney_verified'   => 'boolean',
        'refund_no_quorum_full' => 'boolean',
        'payment_for_rentals' => 'boolean',
        'fee_methods'         => 'array',
        'yookassa_receipt_enabled' => 'boolean',
    ];

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function isYoomoneyReady(): bool
    {
        return $this->yoomoney_enabled
            && !empty($this->yoomoney_shop_id)
            && !empty($this->yoomoney_secret_key);
    }

    public function hasAnyPayment(): bool
    {
        return $this->isYoomoneyReady()
            || !empty($this->tbank_link)
            || !empty($this->sber_link);
    }

    public function availableMethods(): array
    {
        $methods = ['cash'];
        if (!empty($this->tbank_link))   $methods[] = 'tbank_link';
        if (!empty($this->sber_link))    $methods[] = 'sber_link';
        if ($this->yoomoney_verified)    $methods[] = 'yoomoney';
        return $methods;
    }

    public static function availableMethodsFor(int $organizerId): array
    {
        $settings = static::where('organizer_id', $organizerId)->first();
        return $settings ? $settings->availableMethods() : ['cash'];
    }
}
