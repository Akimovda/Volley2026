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
    ];

    protected $casts = [
        'yoomoney_verified' => 'boolean',
    ];
}
