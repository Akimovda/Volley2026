<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPhotoFromProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TelegramAuthController extends Controller
{
    private function storeReturnTo(Request $request): void
    {
        $returnTo = (string) (
            $request->query('return')
            ?: url()->previous()
            ?: url('/events')
        );
        $request->session()->put('oauth_return_to', $this->sanitizeReturnTo($returnTo));
    }

    private function popReturnTo(Request $request): string
    {
        $fromSession = (string) $request->session()->pull('oauth_return_to', '');
        if ($fromSession !== '') return $fromSession;

        $fromQuery = (string) $request->query('return', '');
        if ($fromQuery !== '') return $this->sanitizeReturnTo($fromQuery);

        return $this->sanitizeReturnTo(
            (string) (url()->previous() ?: url('/events'))
        );
    }

    private function sanitizeReturnTo(string $url): string
    {
        $fallback = url('/events');
        $url = trim($url);
        if ($url === '') return $fallback;

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        if ($urlHost && $appHost && strcasecmp($urlHost, $appHost) !== 0) {
            return $fallback;
        }

        if (
            str_contains($url, '/auth/') ||
            str_contains($url, '/login') ||
            str_contains($url, '/register') ||
            str_contains($url, '/logout')
        ) {
            return $fallback;
        }

        return $url;
    }

    public function redirect(Request $request)
    {
        $this->storeReturnTo($request);
        $request->session()->put('oauth_provider', 'telegram');
        $request->session()->put('oauth_intent', Auth::check() ? 'link' : 'login');
        return redirect()->back();
    }

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        $tgData = array_filter(
            $request->only(['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash']),
            static fn ($v) => !is_null($v) && $v !== ''
        );

        \Illuminate\Support\Facades\Log::info('TG_AUTH_DEBUG', [
            'tgData_keys' => array_keys($tgData),
            'tgData' => $tgData,
            'all_query' => $request->query(),
            'bot_token_len' => strlen((string) config('services.telegram.bot_token')),
        ]);
        if (!$this->isTelegramLoginValid($tgData)) {
            return redirect()->to($returnTo)->with('error', 'Telegram auth: invalid signature');
        }

        $authDate = (int) ($tgData['auth_date'] ?? 0);
        if ($authDate <= 0 || (time() - $authDate) > 86400) {
            return redirect()->to($returnTo)->with('error', 'Telegram auth: expired data');
        }

        $tgId = (string) ($tgData['id'] ?? '');
        if ($tgId === '') {
            return redirect()->to($returnTo)->with('error', 'Telegram auth: missing id');
        }

        $username = $tgData['username'] ?? null;
        $photoUrl = $tgData['photo_url'] ?? null;

        $intentFromQuery = (string) $request->query('intent', '');
        $intentFromQuery = in_array($intentFromQuery, ['login', 'link'], true) ? $intentFromQuery : '';

        $intent = $intentFromQuery !== ''
            ? $intentFromQuery
            : (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');

        $request->session()->forget('oauth_provider');

        /* LINK */
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->to($returnTo)->with('error', 'Сначала войдите, чтобы привязать Telegram.');
            }

            $current = $request->user();

            if ((string)($current->telegram_id ?? '') === $tgId) {
                return redirect()->to($returnTo)->with('status', 'Telegram уже привязан ✅');
            }

            if (User::where('telegram_id', $tgId)->where('id', '!=', $current->id)->exists()) {
                return redirect('/user/profile')->with('error', 'Этот Telegram уже привязан к другому аккаунту.');
            }

            $current->telegram_id = $tgId;

            if (!empty($username) && $current->isFillable('telegram_username') && empty($current->telegram_username)) {
                $current->telegram_username = $username;
            }

            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed($current, $photoUrl, false);

            $request->session()->put('auth_provider', 'telegram');
            $request->session()->put('auth_provider_id', $tgId);

            return redirect()->to($returnTo)->with('status', 'Telegram привязан ✅');
        }

        /* LOGIN */
        $user = User::where('telegram_id', $tgId)->first();
        $isNewUser = false;

        if (!$user) {
            $user = User::where('telegram_notify_chat_id', $tgId)->first();
            if ($user) {
                $user->telegram_id = $tgId;
                if (!empty($username) && $user->isFillable('telegram_username') && empty($user->telegram_username)) {
                    $user->telegram_username = $username;
                }
                $user->save();
            }
        }

        if (!$user) {
            $isNewUser = true;

            $email = "tg_{$tgId}@telegram.local";
            if (User::where('email', $email)->exists()) {
                $email = "tg_{$tgId}_" . now()->timestamp . "@telegram.local";
            }

            $user = new User();
            $user->email      = $email;
            $user->password   = Hash::make(Str::random(32));
            $user->name        = trim(($tgData['first_name'] ?? '') . ' ' . ($tgData['last_name'] ?? '')) ?: 'Пользователь';
            $user->telegram_id = $tgId;

            if (!empty($username)) {
                $user->telegram_username = $username;
            }

            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        UserPhotoFromProviderService::seedFromProviderIfAllowed($user, $photoUrl, $isNewUser);

        $request->session()->put('auth_provider', 'telegram');
        $request->session()->put('auth_provider_id', $tgId);

        if ($isNewUser) {
            return redirect()->route('profile.complete')
                ->with('welcome', true);
        }

        return redirect()->to($returnTo);
    }

    private function isTelegramLoginValid(array $data): bool
    {
        if (empty($data['hash'])) return false;

        $hash = (string) $data['hash'];
        unset($data['hash']);

        $data = array_filter($data, static fn ($v) => !is_null($v) && $v !== '');
        ksort($data);

        $checkString = collect($data)->map(fn ($v, $k) => "{$k}={$v}")->implode("\n");
        $botToken = (string) config('services.telegram.bot_token');
        if ($botToken === '') return false;

        $secretKey = hash('sha256', $botToken, true);
        $calcHash  = hash_hmac('sha256', $checkString, $secretKey);

        return hash_equals($calcHash, $hash);
    }
}
