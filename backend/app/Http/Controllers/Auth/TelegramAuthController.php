<?php

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
     * ЕДИНООБРАЗНЫЙ redirect():
     * - записываем oauth_provider / oauth_intent
     * - auth_provider НЕ трогаем
     *
     * В Telegram "redirect" — это просто подготовка сессии и перевод на страницу,
     * где вставлен Telegram Login Widget.
     */
    public function redirect(Request $request)
    {
        $isLink = $request->boolean('link');

        if ($isLink && !Auth::check()) {
            return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать Telegram.');
        }

        $request->session()->put('oauth_provider', 'telegram');
        $request->session()->put('oauth_intent', ($isLink && Auth::check()) ? 'link' : 'login');

        return $isLink ? redirect('/user/profile') : redirect()->route('login');
    }

    /**
     * Callback от Telegram Login Widget.
     *
     * Telegram шлёт: id, first_name, last_name, username, photo_url, auth_date, hash
     */
    public function callback(Request $request)
    {
        $data = $request->all();

        if (!$this->isTelegramLoginValid($data)) {
            return redirect()->route('login')->with('error', 'Telegram auth: invalid signature.');
        }

        $authDate = (int)($data['auth_date'] ?? 0);
        if ($authDate <= 0 || (time() - $authDate) > 86400) {
            return redirect()->route('login')->with('error', 'Telegram auth: expired login data.');
        }

        $tgId      = (string)($data['id'] ?? '');
        $username  = isset($data['username']) ? (string)$data['username'] : null;
        $firstName = isset($data['first_name']) ? (string)$data['first_name'] : null;
        $lastName  = isset($data['last_name']) ? (string)$data['last_name'] : null;
        $photoUrl  = isset($data['photo_url']) ? (string)$data['photo_url'] : null;

        if ($tgId === '') {
            return redirect()->route('login')->with('error', 'Telegram auth: missing id.');
        }

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');
        $request->session()->forget('oauth_provider');

        // =========================
        // LINK
        // =========================
        if ($intent === 'link' && Auth::check()) {
            /** @var User $current */
            $current = Auth::user();

            $existsForOther = User::where('telegram_id', $tgId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')->with('error', 'Этот Telegram уже привязан к другому аккаунту.');
            }

            if ($current->isFillable('telegram_id')) $current->telegram_id = $tgId;
            if ($current->isFillable('telegram_username') && !empty($username) && empty($current->telegram_username)) {
                $current->telegram_username = $username;
            }

            // avatar -> только если пусто
            $baseName = ProfilePhotoService::storeProviderAvatarBasenameIfMissing(
                userId: (int) $current->id,
                avatarUrl: $photoUrl,
                currentProfilePhotoPath: $current->profile_photo_path ?? null
            );
            if (!empty($baseName) && $current->isFillable('profile_photo_path')) {
                $current->profile_photo_path = $baseName;
            }

            $current->save();

            $request->session()->put('auth_provider', 'telegram');
            $request->session()->put('auth_provider_id', $tgId);

            return redirect('/user/profile')->with('status', 'Telegram привязан ✅');
        }

        // =========================
        // LOGIN
        // =========================
        $user = User::where('telegram_id', $tgId)->first();

        if (!$user) {
            $fullName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
            $displayName = $fullName !== '' ? $fullName : "Telegram User #{$tgId}";

            // users.email NOT NULL -> безопасный email
            $safeEmail = "tg_{$tgId}@telegram.local";
            if (User::where('email', $safeEmail)->exists()) {
                $safeEmail = "tg_{$tgId}_" . now()->timestamp . "@telegram.local";
            }

            $user = new User();
            if ($user->isFillable('name')) $user->name = $displayName;
            if ($user->isFillable('email')) $user->email = $safeEmail;
            if ($user->isFillable('password')) $user->password = Hash::make(str()->random(32));

            if ($user->isFillable('telegram_id')) $user->telegram_id = $tgId;
            if ($user->isFillable('telegram_username') && !empty($username)) $user->telegram_username = $username;

            $user->save();
        }

        // avatar -> только если пусто
        $baseName = ProfilePhotoService::storeProviderAvatarBasenameIfMissing(
            userId: (int) $user->id,
            avatarUrl: $photoUrl,
            currentProfilePhotoPath: $user->profile_photo_path ?? null
        );
        if (!empty($baseName) && $user->isFillable('profile_photo_path')) {
            $user->profile_photo_path = $baseName;
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
     * secret_key = sha256(bot_token) (raw bytes)
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
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($calculatedHash, $hash);
    }
}
