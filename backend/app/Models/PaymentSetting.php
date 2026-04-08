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
    ];

    protected $casts = [
        'yoomoney_enabled'    => 'boolean',
        'yoomoney_verified'   => 'boolean',
        'refund_no_quorum_full' => 'boolean',
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
}
