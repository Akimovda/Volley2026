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
    /* =======================================================================
     | RETURN TO
     | ======================================================================= */

    private function storeReturnTo(Request $request): void
    {
        $returnTo = (string) (
            $request->query('return')
            ?: url()->previous()
            ?: url('/events')
        );

        $request->session()->put(
            'oauth_return_to',
            $this->sanitizeReturnTo($returnTo)
        );
    }

    private function popReturnTo(Request $request): string
    {
        $fromSession = (string) $request->session()->pull('oauth_return_to', '');
        if ($fromSession !== '') {
            return $fromSession;
        }

        $fromQuery = (string) $request->query('return', '');
        if ($fromQuery !== '') {
            return $this->sanitizeReturnTo($fromQuery);
        }

        // важно для Telegram Widget: обычно нет redirect() шага, поэтому берём previous()
        return $this->sanitizeReturnTo(
            (string) (url()->previous() ?: url('/events'))
        );
    }

    private function sanitizeReturnTo(string $url): string
    {
        $fallback = url('/events');
        $url = trim($url);

        if ($url === '') {
            return $fallback;
        }

        // Запрещаем уход с домена
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        if ($urlHost && $appHost && strcasecmp($urlHost, $appHost) !== 0) {
            return $fallback;
        }

        // Никогда не возвращаемся в auth-flow
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

    /* =======================================================================
     | REDIRECT (служебный, если когда-нибудь понадобится)
     | ======================================================================= */

    public function redirect(Request $request)
    {
        $this->storeReturnTo($request);

        $request->session()->put('oauth_provider', 'telegram');
        $request->session()->put('oauth_intent', Auth::check() ? 'link' : 'login');

        return redirect()->back();
    }

    /* =======================================================================
     | CALLBACK (Единственная точка логина/привязки)
     | ======================================================================= */

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        // Берём ТОЛЬКО поля Telegram.
        // Важно: не включаем null/'' в подпись (а Telegram их обычно и не присылает).
        $tgData = array_filter(
            $request->only([
                'id',
                'first_name',
                'last_name',
                'username',
                'photo_url',
                'auth_date',
                'hash',
            ]),
            static fn ($v) => !is_null($v) && $v !== ''
        );

        if (!$this->isTelegramLoginValid($tgData)) {
            return redirect()->to($returnTo)
                ->with('error', 'Telegram auth: invalid signature');
        }

        $authDate = (int) ($tgData['auth_date'] ?? 0);
        if ($authDate <= 0 || (time() - $authDate) > 86400) {
            return redirect()->to($returnTo)
                ->with('error', 'Telegram auth: expired data');
        }

        $tgId = (string) ($tgData['id'] ?? '');
        if ($tgId === '') {
            return redirect()->to($returnTo)
                ->with('error', 'Telegram auth: missing id');
        }

        $username  = $tgData['username']   ?? null;
        $firstName = $tgData['first_name'] ?? null;
        $lastName  = $tgData['last_name']  ?? null;
        $photoUrl  = $tgData['photo_url']  ?? null;

        // intent: login/link
        // 1) query (Telegram widget удобно дергать callback сразу с intent=link)
        // 2) session (если был вызван redirect())
        // 3) fallback: если залогинен — link, иначе login
        $intentFromQuery = (string) $request->query('intent', '');
        $intentFromQuery = in_array($intentFromQuery, ['login', 'link'], true) ? $intentFromQuery : '';

        $intent = $intentFromQuery !== ''
            ? $intentFromQuery
            : (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');

        $request->session()->forget('oauth_provider');

        /* ===================================================================
         | LINK (привязка к текущему аккаунту)
         | =================================================================== */
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->to($returnTo)
                    ->with('error', 'Сначала войдите, чтобы привязать Telegram.');
            }

            /** @var User $current */
            $current = $request->user();

            // если уже привязано к этому же юзеру — просто ок
            if ((string)($current->telegram_id ?? '') === $tgId) {
                return redirect()->to($returnTo)->with('status', 'Telegram уже привязан ✅');
            }

            $existsForOther = User::query()
                ->where('telegram_id', $tgId)
                ->where('id', '!=', $current->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')
                    ->with('error', 'Этот Telegram уже привязан к другому аккаунту.');
            }

            if ($current->isFillable('telegram_id')) {
                $current->telegram_id = $tgId;
            }

            if (!empty($username) && $current->isFillable('telegram_username') && empty($current->telegram_username)) {
                $current->telegram_username = $username;
            }

            $current->save();

            UserPhotoFromProviderService::seedFromProviderIfAllowed(
                $current,
                $photoUrl,
                false
            );

            $request->session()->put('auth_provider', 'telegram');
            $request->session()->put('auth_provider_id', $tgId);

            return redirect()->to($returnTo)->with('status', 'Telegram привязан ✅');
        }

        /* ===================================================================
         | LOGIN (по telegram_id)
         | =================================================================== */

        $user = User::query()->where('telegram_id', $tgId)->first();
        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            $fullName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
            $name = $fullName !== '' ? $fullName : "Telegram User #{$tgId}";

            $email = "tg_{$tgId}@telegram.local";
            if (User::where('email', $email)->exists()) {
                $email = "tg_{$tgId}_" . now()->timestamp . "@telegram.local";
            }

            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make(Str::random(32));
            $user->telegram_id = $tgId;

            if (!empty($username)) {
                $user->telegram_username = $username;
            }

            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        UserPhotoFromProviderService::seedFromProviderIfAllowed(
            $user,
            $photoUrl,
            $isNewUser
        );

        $request->session()->put('auth_provider', 'telegram');
        $request->session()->put('auth_provider_id', $tgId);

        return redirect()->to($returnTo);
    }

    /* =======================================================================
     | TELEGRAM SIGNATURE
     | ======================================================================= */

    private function isTelegramLoginValid(array $data): bool
    {
        if (empty($data['hash'])) {
            return false;
        }

        $hash = (string) $data['hash'];
        unset($data['hash']);

        // критично: не включаем пустые ключи в checkString
        $data = array_filter($data, static fn ($v) => !is_null($v) && $v !== '');

        ksort($data);

        $checkString = collect($data)
            ->map(fn ($v, $k) => "{$k}={$v}")
            ->implode("\n");

        $botToken = (string) config('services.telegram.bot_token');
        if ($botToken === '') {
            return false;
        }

        $secretKey = hash('sha256', $botToken, true);
        $calcHash = hash_hmac('sha256', $checkString, $secretKey);

        return hash_equals($calcHash, $hash);
    }
}
