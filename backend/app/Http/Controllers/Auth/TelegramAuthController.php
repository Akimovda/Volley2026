<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserPhotoFromProviderService;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramAuthController extends Controller
{
    private const AUTH_URL  = 'https://oauth.telegram.org/auth';
    private const TOKEN_URL = 'https://oauth.telegram.org/token';
    private const JWKS_URL  = 'https://oauth.telegram.org/.well-known/jwks.json';

    /* ── helpers: return URL ── */

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

    /* ── PKCE helpers ── */

    private function generateCodeVerifier(): string
    {
        return Str::random(64);
    }

    private function generateCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /* ── STEP 1: redirect to Telegram ── */

    public function redirect(Request $request)
    {
        $this->storeReturnTo($request);

        $intent = Auth::check() ? 'link' : 'login';
        $state = Str::random(40);
        $codeVerifier = $this->generateCodeVerifier();

        $request->session()->put('telegram_oidc_state', $state);
        $request->session()->put('telegram_oidc_verifier', $codeVerifier);
        $request->session()->put('oauth_intent', $intent);

        // Резервное хранилище state в Cache (10 мин) — на случай если сессионная кука
        // не вернётся при cross-domain redirect (Telegram WebView, системный браузер).
        Cache::put("tg_oidc_{$state}", [
            'verifier'  => $codeVerifier,
            'intent'    => $intent,
            'return_to' => $request->session()->get('oauth_return_to', url('/events')),
        ], now()->addMinutes(10));

        // Явно сохраняем сессию до ухода на Telegram — гарантирует, что state
        // попадёт в БД прежде чем браузер покинет наш домен.
        $request->session()->save();

        $params = [
            'client_id'             => config('services.telegram.oidc_client_id'),
            'redirect_uri'          => route('auth.telegram.callback'),
            'response_type'         => 'code',
            'scope'                 => 'openid profile telegram:bot_access',
            'state'                 => $state,
            'code_challenge'        => $this->generateCodeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ];

        return redirect(self::AUTH_URL . '?' . http_build_query($params));
    }

    /* ── STEP 2: callback from Telegram ── */

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        // ── Legacy widget support (fallback) ──
        if ($request->has('hash') && $request->has('id')) {
            return $this->handleLegacyCallback($request, $returnTo);
        }

        // ── OIDC flow ──
        if ($request->has('error')) {
            Log::warning('Telegram OIDC error', ['error' => $request->query('error')]);
            return redirect()->to($returnTo)->with('error', 'Telegram: ошибка авторизации');
        }

        $code  = $request->query('code');
        $state = $request->query('state');

        if (!$code || !$state) {
            return redirect()->to($returnTo)->with('error', 'Telegram: некорректный ответ');
        }

        // Verify state — сначала из сессии, при потере (Telegram WebView → system browser) из Cache
        $savedState   = $request->session()->pull('telegram_oidc_state');
        $codeVerifier = $request->session()->pull('telegram_oidc_verifier');
        $intent       = (string) $request->session()->pull('oauth_intent', '');

        if (!$savedState || !hash_equals($savedState, $state)) {
            // Fallback: ищем в Cache по значению state
            $cached = Cache::pull("tg_oidc_{$state}");
            if ($cached) {
                $savedState   = $state;   // state совпадает с ключом — CSRF-защита сохранена
                $codeVerifier = $cached['verifier'];
                $intent       = $cached['intent'];
                $returnTo     = $cached['return_to'] ?: $returnTo;
                Log::info('TG_OIDC: cache fallback used (session cookie lost)');
            } else {
                return redirect()->to($returnTo)->with('error', 'Telegram: неверный state');
            }
        }

        if (empty($intent)) {
            $intent = Auth::check() ? 'link' : 'login';
        }

        // Exchange code for tokens
        $clientId     = config('services.telegram.oidc_client_id');
        $clientSecret = config('services.telegram.oidc_client_secret');

        $tokenResponse = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post(self::TOKEN_URL, [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => route('auth.telegram.callback'),
                'client_id'     => $clientId,
                'code_verifier' => $codeVerifier,
            ]);

        if (!$tokenResponse->successful()) {
            Log::error('Telegram OIDC token exchange failed', [
                'status' => $tokenResponse->status(),
                'body'   => $tokenResponse->body(),
            ]);
            return redirect()->to($returnTo)->with('error', 'Telegram: ошибка получения токена');
        }

        $tokens  = $tokenResponse->json();
        $idToken = $tokens['id_token'] ?? null;

        if (!$idToken) {
            return redirect()->to($returnTo)->with('error', 'Telegram: отсутствует id_token');
        }

        // Validate JWT
        try {
            $claims = $this->validateIdToken($idToken, $clientId);
        } catch (\Exception $e) {
            Log::error('Telegram OIDC JWT validation failed', ['error' => $e->getMessage()]);
            return redirect()->to($returnTo)->with('error', 'Telegram: ошибка валидации токена');
        }

        $tgId     = (string) ($claims->id ?? $claims->sub ?? '');
        $name     = $claims->name ?? '';
        $username = $claims->preferred_username ?? null;
        $photoUrl = $claims->picture ?? null;

        if ($tgId === '') {
            return redirect()->to($returnTo)->with('error', 'Telegram: отсутствует ID пользователя');
        }

        Log::info('TG_OIDC_AUTH', [
            'tg_id'    => $tgId,
            'name'     => $name,
            'username' => $username,
            'intent'   => $intent,
        ]);

        return $this->processAuth($request, $tgId, $name, $username, $photoUrl, $intent, $returnTo);
    }

    /* ── JWT validation ── */

    private function validateIdToken(string $idToken, string $clientId): object
    {
        $jwks = Cache::remember('telegram_oidc_jwks', 3600, function () {
            $response = Http::get(self::JWKS_URL);
            return $response->json();
        });

        $keys = JWK::parseKeySet($jwks);
        $decoded = JWT::decode($idToken, $keys);

        // Verify claims
        if (($decoded->iss ?? '') !== 'https://oauth.telegram.org') {
            throw new \RuntimeException('Invalid issuer');
        }
        if ((string)($decoded->aud ?? '') !== (string)$clientId) {
            throw new \RuntimeException('Invalid audience');
        }

        return $decoded;
    }

    /* ── Common auth logic (shared between OIDC and legacy) ── */

    private function processAuth(Request $request, string $tgId, string $name, ?string $username, ?string $photoUrl, string $intent, string $returnTo)
    {
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

            $nameParts = explode(' ', trim($name), 2);

            $user = new User();
            $user->email      = $email;
            $user->password   = Hash::make(Str::random(32));
            $user->name       = trim($name) ?: 'Пользователь';
            $user->first_name = $nameParts[0] ?? '';
            $user->last_name  = $nameParts[1] ?? '';
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

    /* ── Telegram Mini App auth (initData) ── */

    public function miniapp(Request $request)
    {
        $initData = (string) $request->input('init_data', '');
        $returnTo = $this->sanitizeReturnTo((string) $request->input('return_to', url('/events')));

        if ($initData === '') {
            return response()->json(['error' => 'No init_data'], 400);
        }

        parse_str($initData, $params);
        $hash = (string) ($params['hash'] ?? '');
        unset($params['hash']);

        if ($hash === '') {
            return response()->json(['error' => 'No hash'], 400);
        }

        ksort($params);
        $checkString = collect($params)->map(fn($v, $k) => "{$k}={$v}")->implode("\n");
        $botToken    = (string) config('services.telegram.bot_token');
        $secretKey   = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calcHash    = hash_hmac('sha256', $checkString, $secretKey);

        if (!hash_equals($calcHash, $hash)) {
            Log::warning('TG_MINIAPP: invalid hash');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $authDate = (int) ($params['auth_date'] ?? 0);
        if ($authDate <= 0 || (time() - $authDate) > 86400) {
            return response()->json(['error' => 'Expired initData'], 403);
        }

        $userData = json_decode($params['user'] ?? '{}', true) ?? [];
        $tgId     = (string) ($userData['id'] ?? '');
        $name     = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
        $username = $userData['username'] ?? null;

        if ($tgId === '') {
            return response()->json(['error' => 'No user id'], 400);
        }

        Log::info('TG_MINIAPP_AUTH', ['tg_id' => $tgId, 'name' => $name]);

        $intent = Auth::check() ? 'link' : 'login';
        $request->session()->forget('oauth_provider');

        if ($intent === 'link') {
            $current = $request->user();

            if ((string) ($current->telegram_id ?? '') === $tgId) {
                return response()->json(['redirect' => $returnTo]);
            }

            if (User::where('telegram_id', $tgId)->where('id', '!=', $current->id)->exists()) {
                return response()->json(['error' => 'already_used', 'message' => 'Этот Telegram уже привязан к другому аккаунту.'], 422);
            }

            $current->telegram_id = $tgId;
            if (!empty($username) && $current->isFillable('telegram_username') && empty($current->telegram_username)) {
                $current->telegram_username = $username;
            }
            $current->save();
            $request->session()->put('auth_provider', 'telegram');

            return response()->json(['redirect' => $returnTo]);
        }

        // LOGIN
        $user      = User::where('telegram_id', $tgId)->first();
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
            $nameParts = explode(' ', trim($name), 2);

            $user             = new User();
            $user->email      = $email;
            $user->password   = Hash::make(Str::random(32));
            $user->name       = trim($name) ?: 'Пользователь';
            $user->first_name = $nameParts[0] ?? '';
            $user->last_name  = $nameParts[1] ?? '';
            $user->telegram_id = $tgId;
            if (!empty($username)) {
                $user->telegram_username = $username;
            }
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('auth_provider', 'telegram');

        return response()->json([
            'redirect' => $isNewUser ? route('profile.complete') : $returnTo,
        ]);
    }

    /* ── Legacy widget fallback ── */

    private function handleLegacyCallback(Request $request, string $returnTo)
    {
        $tgData = array_filter(
            $request->only(['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash']),
            static fn ($v) => !is_null($v) && $v !== ''
        );

        if (!$this->isLegacyHashValid($tgData)) {
            return redirect()->to($returnTo)->with('error', 'Telegram auth: invalid signature');
        }

        $authDate = (int) ($tgData['auth_date'] ?? 0);
        if ($authDate <= 0 || (time() - $authDate) > 86400) {
            return redirect()->to($returnTo)->with('error', 'Telegram auth: expired data');
        }

        $tgId     = (string) ($tgData['id'] ?? '');
        $name     = trim(($tgData['first_name'] ?? '') . ' ' . ($tgData['last_name'] ?? ''));
        $username = $tgData['username'] ?? null;
        $photoUrl = $tgData['photo_url'] ?? null;
        $intent   = Auth::check() ? 'link' : 'login';

        return $this->processAuth($request, $tgId, $name, $username, $photoUrl, $intent, $returnTo);
    }

    private function isLegacyHashValid(array $data): bool
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
