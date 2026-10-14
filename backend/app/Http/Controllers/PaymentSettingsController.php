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
        $canUseForRentals = $user->is_club_manager && $user->ownedLocations()->exists();

        return view('payment.settings', compact('settings', 'canUseForRentals'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $canUseForRentals = $user->is_club_manager && $user->ownedLocations()->exists();

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
            'payment_for_rentals'  => ['sometimes', 'boolean'],
            'yookassa_receipt_enabled' => ['sometimes', 'boolean'],
            'offer_choice'         => ['sometimes', 'boolean'],
            'offer_methods'        => ['nullable', 'array'],
            'offer_methods.*'      => ['in:cash,tbank_link,sber_link,yoomoney'],
            'yookassa_vat_code'    => ['nullable', 'integer', 'between:1,12'],
            'yookassa_tax_system_code' => ['nullable', 'integer', 'between:1,6'],
        ] + \App\Support\PaymentPricing::rules());

        $pricing = \App\Support\PaymentPricing::fromRequest($data);
        $data = array_diff_key($data, array_flip(array_map(fn ($k) => 'fee_m_' . $k, array_keys(\App\Support\PaymentPricing::METHODS))));

        $yooEnabled = (bool)($data['yoomoney_enabled'] ?? false);
        $yooVerified = $yooEnabled
            && !empty($data['yoomoney_shop_id'])
            && !empty($data['yoomoney_secret_key']);

        PaymentSetting::updateOrCreate(
            ['organizer_id' => $user->id],
            array_merge($data, [
                'yoomoney_enabled'  => $yooEnabled,
                'yookassa_receipt_enabled' => (bool) ($data['yookassa_receipt_enabled'] ?? false),
                'offer_choice'      => (bool) ($data['offer_choice'] ?? false),
                'offer_methods'     => array_values($data['offer_methods'] ?? []),
                'yookassa_vat_code' => (int) ($data['yookassa_vat_code'] ?? 1) ?: 1,
                'yookassa_tax_system_code' => !empty($data['yookassa_tax_system_code']) ? (int) $data['yookassa_tax_system_code'] : null,
                'yoomoney_verified' => $yooVerified,
                'payment_for_rentals' => $canUseForRentals && (bool)($data['payment_for_rentals'] ?? false),
            ], $pricing)
        );

        return back()->with('status', '✅ Настройки оплаты сохранены!');
    }
}
