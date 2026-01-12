<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ProfilePhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TelegramAuthController extends Controller
{
    /**
     * ------------------------------------------------------------
     * REDIRECT (единый контракт)
     * ------------------------------------------------------------
     * Telegram Login Widget не делает OAuth redirect, но нам нужен единый контракт:
     * - oauth_provider/oauth_intent
     * - НЕ трогать auth_provider
     * - редирект на страницу, где стоит виджет (login / profile)
     */
    public function redirect(Request $request)
    {
        $isLink = $request->boolean('link');

        $request->session()->put('oauth_provider', 'telegram');
        $request->session()->put('oauth_intent', ($isLink && Auth::check()) ? 'link' : 'login');

        if ($isLink && !Auth::check()) {
            return redirect()->route('login')->with('error', 'Сначала войдите в аккаунт, чтобы привязать Telegram.');
        }

        return $isLink
            ? redirect('/user/profile')
            : redirect()->route('login');
    }

    /**
     * ------------------------------------------------------------
     * CALLBACK (единый login/link)
     * ------------------------------------------------------------
     * Telegram виджет присылает:
     * id, first_name, last_name, username, photo_url, auth_date, hash
     */
    public function callback(Request $request)
    {
        $data = $request->all();

        if (!$this->isTelegramLoginValid($data)) {
            return redirect()->route('login')->with('error', 'Telegram auth: invalid signature.');
        }

        $authDate = (int) ($data['auth_date'] ?? 0);
        if ($authDate <= 0 || (time() - $authDate) > 86400) {
            return redirect()->route('login')->with('error', 'Telegram auth: expired login data.');
        }

        $tgId      = (string) ($data['id'] ?? '');
        $username  = isset($data['username']) ? (string) $data['username'] : null;
        $firstName = isset($data['first_name']) ? (string) $data['first_name'] : null;
        $lastName  = isset($data['last_name']) ? (string) $data['last_name'] : null;
        $photoUrl  = isset($data['photo_url']) ? (string) $data['photo_url'] : null;

        if ($tgId === '') {
            return redirect()->route('login')->with('error', 'Telegram auth: missing id.');
        }

        $intent = (string) $request->session()->get('oauth_intent', Auth::check() ? 'link' : 'login');

        // =========================
        // MODE: LINK
        // =========================
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сессия истекла. Войдите и повторите привязку Telegram.');
            }

            /** @var User $current */
            $current = Auth::user();

            $existsForOther = User::query()
                ->where('telegram_id', $tgId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')->with('error', 'Этот Telegram уже привязан к другому аккаунту.');
            }

            if ($current->isFillable('telegram_id')) $current->telegram_id = $tgId;
            if ($current->isFillable('telegram_username')) $current->telegram_username = $username;

            // аккуратно дополним имена, если пустые
            if ($current->isFillable('first_name') && !empty($firstName) && empty($current->first_name)) {
                $current->first_name = $firstName;
            }
            if ($current->isFillable('last_name') && !empty($lastName) && empty($current->last_name)) {
                $current->last_name = $lastName;
            }

            // avatar only if missing
            $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
                userId: (int) $current->id,
                avatarUrl: $photoUrl,
                currentProfilePhotoPath: $current->profile_photo_path ?? null,
            );
            if (!empty($thumbPath) && $current->isFillable('profile_photo_path')) {
                $current->profile_photo_path = $thumbPath;
            }

            $current->save();

            $request->session()->put('auth_provider', 'telegram');
            $request->session()->put('auth_provider_id', $tgId);

            return redirect('/user/profile')->with('status', 'Telegram привязан ✅');
        }

        // =========================
        // MODE: LOGIN
        // =========================
        $user = User::query()->where('telegram_id', $tgId)->first();

        if (!$user) {
            $fullName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
            $safeName = $fullName !== '' ? $fullName : "Telegram User #{$tgId}";

            $safeEmail = "tg_{$tgId}@telegram.local";
            if (User::query()->where('email', $safeEmail)->exists()) {
                $safeEmail = "tg_{$tgId}_" . now()->timestamp . "@telegram.local";
            }

            $user = new User();
            if ($user->isFillable('name'))  $user->name = $safeName;
            if ($user->isFillable('email')) $user->email = $safeEmail;
            if ($user->isFillable('password')) $user->password = Hash::make(str()->random(32));

            if ($user->isFillable('telegram_id')) $user->telegram_id = $tgId;
            if ($user->isFillable('telegram_username')) $user->telegram_username = $username;
            if ($user->isFillable('first_name')) $user->first_name = $firstName;
            if ($user->isFillable('last_name'))  $user->last_name = $lastName;

            $user->save();
        }

        // avatar only if missing
        $thumbPath = ProfilePhotoService::storeProviderAvatarIfMissing(
            userId: (int) $user->id,
            avatarUrl: $photoUrl,
            currentProfilePhotoPath: $user->profile_photo_path ?? null,
        );
        if (!empty($thumbPath) && $user->isFillable('profile_photo_path')) {
            $user->profile_photo_path = $thumbPath;
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'telegram');
        $request->session()->put('auth_provider_id', $tgId);

        return redirect()->intended('/events');
    }

    /**
     * Проверка подписи Telegram Login Widget.
     * secret_key = sha256(bot_token) (binary), signature = HMAC-SHA256(data_check_string, secret_key)
     */
    private function isTelegramLoginValid(array $data): bool
    {
        if (empty($data['hash'])) return false;

        $hash = (string) $data['hash'];
        unset($data['hash']);

        ksort($data);

        $pairs = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) continue;
            $pairs[] = $k . '=' . $v;
        }

        $dataCheckString = implode("\n", $pairs);

        $botToken = (string) config('services.telegram.bot_token');
        if ($botToken === '') return false;

        $secretKey = hash('sha256', $botToken, true);
        $calculated = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($calculated, $hash);
    }
}
