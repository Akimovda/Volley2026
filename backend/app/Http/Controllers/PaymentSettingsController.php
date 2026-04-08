<?php
namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class PaymentSettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $settings = PaymentSetting::firstOrNew(['organizer_id' => $user->id]);

        return view('payment.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'default_method'       => ['required', 'in:cash,tbank_link,sber_link,yoomoney'],
            'tbank_link'           => ['nullable', 'url', 'max:500'],
            'sber_link'            => ['nullable', 'url', 'max:500'],
            'yoomoney_shop_id'     => ['nullable', 'string', 'max:100'],
            'yoomoney_secret_key'  => ['nullable', 'string', 'max:200'],
            'yoomoney_enabled'     => ['sometimes', 'boolean'],
            'refund_hours_full'    => ['required', 'integer', 'min:0', 'max:720'],
            'refund_hours_partial' => ['required', 'integer', 'min:0', 'max:720'],
            'refund_partial_pct'   => ['required', 'integer', 'min:0', 'max:100'],
            'refund_no_quorum_full'=> ['sometimes', 'boolean'],
            'payment_hold_minutes' => ['required', 'integer', 'min:5', 'max:120'],
        ]);

        $yooEnabled = (bool)($data['yoomoney_enabled'] ?? false);
        $yooVerified = $yooEnabled
            && !empty($data['yoomoney_shop_id'])
            && !empty($data['yoomoney_secret_key']);

        PaymentSetting::updateOrCreate(
            ['organizer_id' => $user->id],
            array_merge($data, [
                'yoomoney_enabled'  => $yooEnabled,
                'yoomoney_verified' => $yooVerified,
            ])
        );

        return back()->with('status', '✅ Настройки оплаты сохранены!');
    }
}
