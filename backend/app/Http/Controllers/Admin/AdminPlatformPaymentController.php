<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformPaymentSetting;
use Illuminate\Http\Request;

class AdminPlatformPaymentController extends Controller
{
    public function edit()
    {
        $settings = PlatformPaymentSetting::first() ?? new PlatformPaymentSetting();
        return view('admin.platform_payment_settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'method'            => 'required|in:tbank_link,sber_link,yoomoney',
            'tbank_link'        => 'nullable|url',
            'sber_link'         => 'nullable|url',
            'yoomoney_shop_id'  => 'nullable|string',
            'yoomoney_secret_key' => 'nullable|string',
        ]);

        $settings = PlatformPaymentSetting::first() ?? new PlatformPaymentSetting();

        $settings->method     = $request->method;
        $settings->tbank_link = $request->tbank_link;
        $settings->sber_link  = $request->sber_link;
        $settings->yoomoney_shop_id = $request->yoomoney_shop_id;

        if ($request->filled('yoomoney_secret_key') && $request->yoomoney_secret_key !== '••••••••') {
            $settings->yoomoney_secret_key = encrypt($request->yoomoney_secret_key);
        }

        $settings->ad_event_price_rub = (int) $request->input('ad_event_price_rub', 0);

        $settings->save();

        return back()->with('status', '✅ Настройки платёжной системы сохранены.');
    }
}
