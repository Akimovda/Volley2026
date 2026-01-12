<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TelegramAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $request->session()->put('oauth_provider', 'telegram');
        $request->session()->put('oauth_intent', Auth::check() ? 'link' : 'login');

        return view('auth.telegram-redirect', [
            'botName' => config('services.telegram.bot_name'),
            'authUrl' => route('auth.telegram.callback'),
        ]);
    }

    public function callback(Request $request)
    {
        $data = $request->query();

        if (!$this->isValidTelegramAuth($data)) {
            abort(403, 'Invalid Telegram authentication');
        }

        if (isset($data['auth_date']) && (time() - (int) $data['auth_date']) > 86400) {
            abort(403, 'Telegram authentication expired');
        }

        $telegramId = (string) ($data['id'] ?? '');
        if ($telegramId === '') abort(403, 'Telegram id missing');

        $telegramUsername = $data['username'] ?? null;
        $telegramUsername = $telegramUsername ? ltrim($telegramUsername, '@') : null;

        $intent = $request->session()->get('oauth_intent', Auth::check() ? 'link' : 'login');

        // ===== LINK =====
        if ($intent === 'link' && Auth::check()) {
            $currentUser = Auth::user();

            $existsForOther = User::where('telegram_id', $telegramId)
                ->where('id', '!=', $currentUser->id)
                ->exists();

            if ($existsForOther) {
                return redirect('/user/profile')
                    ->with('error', 'Этот Telegram уже привязан к другому аккаунту.');
            }

            $currentUser->telegram_id = $telegramId;
            $currentUser->telegram_username = $telegramUsername;
            $currentUser->save();

            $request->session()->put('auth_provider', 'telegram');
            $request->session()->put('auth_provider_id', $telegramId);

            return redirect('/user/profile')->with('status', 'Telegram привязан ✅');
        }

        // ===== LOGIN =====
        $fakeEmail = "tg_{$telegramId}@telegram.local";

        $user = User::where('telegram_id', $telegramId)
            ->orWhere('email', $fakeEmail)
            ->first();

        if (!$user) {
            $user = User::create([
                'name'              => "TG User #{$telegramId}",
                'email'             => $fakeEmail,
                'password'          => Hash::make(str()->random(32)),
                'telegram_id'       => $telegramId,
                'telegram_username' => $telegramUsername,
            ]);
        } else {
            if (empty($user->telegram_id)) {
                $existsForOther = User::where('telegram_id', $telegramId)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($existsForOther) abort(409, 'Этот Telegram уже привязан к другому аккаунту.');

                $user->telegram_id = $telegramId;
            }

            if ($telegramUsername !== $user->telegram_username) {
                $user->telegram_username = $telegramUsername;
            }

            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $request->session()->put('auth_provider', 'telegram');
        $request->session()->put('auth_provider_id', $telegramId);

        return redirect()->intended('/events');
    }

    private function isValidTelegramAuth(array $data): bool
    {
        if (empty($data['hash'])) return false;

        $botToken = config('services.telegram.bot_token');
        if (!$botToken) return false;

        $checkHash = $data['hash'];
        unset($data['hash']);

        ksort($data);

        $dataCheckString = collect($data)
            ->map(fn ($v, $k) => $k . '=' . $v)
            ->implode("\n");

        $secretKey = hash('sha256', $botToken, true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($hash, $checkHash);
    }
}
