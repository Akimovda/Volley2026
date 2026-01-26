<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AccountUnlinkController extends Controller
{
    public function telegram(Request $request)
    {
        return $this->unlink($request, 'telegram');
    }

    public function vk(Request $request)
    {
        return $this->unlink($request, 'vk');
    }

    public function yandex(Request $request)
    {
        return $this->unlink($request, 'yandex');
    }

    private function unlink(Request $request, string $provider)
    {
        /** @var User $u */
        $u = $request->user();

        $providers = [
            'telegram' => !empty($u->telegram_id),
            'vk'       => !empty($u->vk_id),
            'yandex'   => !empty($u->yandex_id),
        ];

        if (empty($providers[$provider])) {
            return back()->with('error', 'Этот вход уже не привязан.');
        }

        // нельзя отвязать последний вход
        $linkedCount = collect($providers)->filter()->count();
        if ($linkedCount <= 1) {
            return back()->with('error', 'Нельзя отвязать последний способ входа. Сначала привяжите другой провайдер.');
        }

        if ($provider === 'telegram') {
            $u->telegram_id = null;
            $u->telegram_username = null;
            $u->telegram_phone = null;
        }

        if ($provider === 'vk') {
            $u->vk_id = null;
            $u->vk_email = null;
            $u->vk_phone = null;
        }

        if ($provider === 'yandex') {
            $u->yandex_id = null;
            $u->yandex_email = null;
            $u->yandex_phone = null;
            $u->yandex_avatar = null;
        }

        $u->save();

        // если отвязали текущий provider в сессии — подчистим метки
        if (session('auth_provider') === $provider) {
            $request->session()->forget(['auth_provider', 'auth_provider_id']);
        }

        $label = match ($provider) {
            'telegram' => 'Telegram',
            'vk' => 'VK',
            'yandex' => 'Yandex',
            default => 'Provider',
        };

        return back()->with('status', "{$label} отвязан ✅");
    }
}
