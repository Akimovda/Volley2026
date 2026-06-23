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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AppleAuthController extends Controller
{
    private function storeReturnTo(Request $request): void
    {
        $returnTo = (string) ($request->query('return') ?: url()->previous() ?: '');
        $request->session()->put('oauth_return_to', $this->sanitizeReturnTo($returnTo));
    }

    private function sanitizeReturnTo(string $url): string
    {
        $fallback = url('/events');
        $url = trim($url);
        if ($url === '') return $fallback;

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        if ($urlHost && $appHost && strcasecmp($urlHost, $appHost) !== 0) return $fallback;
        if (str_contains($url, '/auth/') || str_contains($url, '/logout')) return $fallback;

        return $url;
    }

    private function popReturnTo(Request $request): string
    {
        return (string) $request->session()->pull('oauth_return_to', url('/events'));
    }

    public function redirect(Request $request)
    {
        $intent = $request->boolean('link') ? 'link' : (Auth::check() ? 'link' : 'login');
        $this->storeReturnTo($request);
        $request->session()->put('oauth_intent', $intent);
        $request->session()->save();

        return Socialite::driver('apple')
            ->scopes(['name', 'email'])
            ->stateless()
            ->redirect();
    }

    public function callback(Request $request)
    {
        $returnTo = $this->popReturnTo($request);

        if ($request->has('error')) {
            Log::warning('[APPLE_OAUTH] error', ['error' => $request->input('error')]);
            return redirect()->route('login')->with('error', 'Apple: ошибка авторизации');
        }

        $code = (string) $request->input('code', '');
        if ($code === '') {
            Log::error('[APPLE_OAUTH] missing code', [
                'has_code'  => $request->has('code'),
                'has_token' => $request->has('id_token'),
                'has_user'  => $request->has('user'),
                'has_state' => $request->has('state'),
                'method'    => $request->method(),
                'keys'      => array_keys($request->all()),
            ]);
            return redirect()->route('login')->with('error', 'Не удалось войти через Apple. Попробуйте ещё раз.');
        }

        try {
            $appleUser = Socialite::driver('apple')->stateless()->user();
        } catch (\Throwable $e) {
            Log::error('[APPLE_OAUTH] user() failed', [
                'e'    => $e->getMessage(),
                'code' => substr($code, 0, 8) . '…',
            ]);
            return redirect()->route('login')->with('error', 'Не удалось войти через Apple. Попробуйте ещё раз.');
        }

        $appleId = (string) $appleUser->getId();
        if ($appleId === '') {
            return redirect()->route('login')->with('error', 'Apple: не удалось получить ID пользователя');
        }

        // Имя приходит только при первом входе
        $rawName = trim((string) ($appleUser->getName() ?? ''));
        $email   = (string) ($appleUser->getEmail() ?? '');

        $intent = (string) $request->session()->pull('oauth_intent', Auth::check() ? 'link' : 'login');

        Log::info('[APPLE_OAUTH]', ['apple_id' => $appleId, 'intent' => $intent]);

        /* LINK */
        if ($intent === 'link') {
            if (!Auth::check()) {
                return redirect()->route('login')->with('error', 'Сначала войдите, чтобы привязать Apple ID.');
            }

            $current = $request->user();

            if ((string) ($current->apple_id ?? '') === $appleId) {
                return redirect()->to($returnTo)->with('status', 'Apple ID уже привязан ✅');
            }

            if (User::where('apple_id', $appleId)->where('id', '!=', $current->id)->exists()) {
                return redirect('/user/profile')->with('error', 'Этот Apple ID уже привязан к другому аккаунту.');
            }

            $current->apple_id = $appleId;
            $current->save();

            $request->session()->put('auth_provider', 'apple');
            $request->session()->put('auth_provider_id', $appleId);

            return redirect()->to($returnTo)->with('status', 'Apple ID привязан ✅');
        }

        /* LOGIN */
        $user = User::where('apple_id', $appleId)->first();
        $isNewUser = false;

        if (!$user) {
            $isNewUser = true;

            $safeEmail = $email !== '' ? $email : 'apple_' . Str::random(8) . '@apple.local';
            if (User::where('email', $safeEmail)->exists()) {
                $safeEmail = 'apple_' . Str::random(8) . '@apple.local';
            }

            $user = new User();
            $user->email      = $safeEmail;
            $user->password   = Hash::make(Str::random(32));
            $user->name       = $rawName ?: 'Apple User';
            $user->apple_id   = $appleId;
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'apple');
        $request->session()->put('auth_provider_id', $appleId);

        if ($isNewUser) {
            return redirect()->route('profile.complete')->with('welcome', true);
        }

        return redirect()->to($returnTo);
    }

    public function nativeCallback(Request $request)
    {
        $validated = $request->validate([
            'identity_token'  => 'required|string',
            'user_identifier' => 'required|string',
            'first_name'      => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'email'           => 'nullable|string|email|max:255',
        ]);

        Log::warning('[APPLE_NATIVE] Attempt', ['user_identifier' => $validated['user_identifier']]);

        try {
            $appleKeys = Cache::remember('apple_public_keys', 86400, function () {
                $ctx  = stream_context_create(['http' => ['timeout' => 5]]);
                $json = file_get_contents('https://appleid.apple.com/auth/keys', false, $ctx);
                if ($json === false) {
                    throw new \RuntimeException('Failed to fetch Apple public keys');
                }
                return json_decode($json, true);
            });

            $keySet  = JWK::parseKeySet($appleKeys, 'RS256');
            $decoded = JWT::decode($validated['identity_token'], $keySet);

            if (($decoded->iss ?? '') !== 'https://appleid.apple.com') {
                throw new \RuntimeException('Invalid issuer: ' . ($decoded->iss ?? ''));
            }

            // aud может быть строкой или массивом
            $aud = $decoded->aud ?? '';
            if (is_array($aud)) {
                $aud = $aud[0] ?? '';
            }
            // Нативный iOS токен (ASAuthorizationAppleIDProvider) ставит aud = bundle ID приложения,
            // web-токен — Services ID (client_id). Принимаем оба.
            $validAud = array_filter([
                config('services.apple.client_id'),  // club.volleyplay.app.signin
                config('apn.app_bundle_id', ''),     // club.volleyplay.app
            ]);
            if (!in_array($aud, $validAud, true)) {
                throw new \RuntimeException('Invalid audience: ' . $aud);
            }
        } catch (\Throwable $e) {
            Log::warning('[APPLE_NATIVE] JWT verification failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Не удалось верифицировать Apple ID.'], 422);
        }

        $appleId = (string) ($decoded->sub ?? '');
        if ($appleId === '') {
            Log::warning('[APPLE_NATIVE] empty sub');
            return response()->json(['success' => false, 'error' => 'Apple ID не определён.'], 422);
        }

        // sub в JWT должен совпадать с user_identifier от iOS
        if ($appleId !== $validated['user_identifier']) {
            Log::warning('[APPLE_NATIVE] sub mismatch', [
                'jwt_sub' => $appleId,
                'sent'    => $validated['user_identifier'],
            ]);
            return response()->json(['success' => false, 'error' => 'Apple ID не совпадает.'], 422);
        }

        // email: приоритет из JWT (верифицирован Apple), fallback — из запроса
        $email     = (string) ($decoded->email ?? '');
        if ($email === '') {
            $email = trim((string) ($validated['email'] ?? ''));
        }
        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $lastName  = trim((string) ($validated['last_name'] ?? ''));

        // Поиск пользователя
        $user      = User::where('apple_id', $appleId)->first();
        $isNewUser = false;

        // Не найден по apple_id — поискать по email и привязать
        if (!$user && $email !== '') {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->apple_id = $appleId;
                $user->save();
            }
        }

        // Создать нового
        if (!$user) {
            $isNewUser = true;

            $safeEmail = $email !== '' ? $email : 'apple_' . Str::random(8) . '@apple.local';
            if (User::where('email', $safeEmail)->exists()) {
                $safeEmail = 'apple_' . Str::random(8) . '@apple.local';
            }

            $rawName = trim($firstName . ' ' . $lastName) ?: 'Apple User';

            $user           = new User();
            $user->email    = $safeEmail;
            $user->password = Hash::make(Str::random(32));
            $user->name     = $rawName;
            $user->apple_id = $appleId;
            if ($firstName !== '') {
                $user->first_name = $firstName;
                $user->last_name  = $lastName;
            }
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('auth_provider', 'apple');
        $request->session()->put('auth_provider_id', $appleId);

        Log::warning('[APPLE_NATIVE] Success', ['user_id' => $user->id, 'is_new' => $isNewUser]);

        return response()->json([
            'success'  => true,
            'is_new'   => $isNewUser,
            'redirect' => $isNewUser ? route('profile.complete') : url('/events'),
            'user'     => [
                'id'   => $user->id,
                'name' => $user->first_name ?: $user->name,
            ],
        ]);
    }
}
