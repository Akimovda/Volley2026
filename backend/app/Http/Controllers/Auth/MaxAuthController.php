<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountLink;
use App\Models\MaxLoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Вход и привязка через MAX по ссылке-приглашению к боту (у MAX нет OAuth для сторонних сайтов):
 *  1) страница создаёт попытку: одноразовый токен для ссылки https://max.ru/<бот>?start=<токен> и 4-значный код;
 *  2) пользователь жмёт «Старт» у бота — бот сообщает серверу идентификатор пользователя MAX (Api\MaxLoginWebhookController::begin);
 *  3) бот просит ввести код, показанный НА САЙТЕ (код видит только браузер, начавший вход, — так «пересланная жертве ссылка»
 *     не даёт злоумышленнику войти в её аккаунт);
 *  4) после верного кода браузер (по опросу status) получает вход: находим/создаём пользователя и логиним в его сессии.
 * Опрос привязан к сессии браузера, начавшего вход: чужой браузер статус попытки не получит.
 */
class MaxAuthController extends Controller
{
    private const SESSION_KEY = 'max_login_attempt';

    public function page(Request $request)
    {
        $intent = Auth::check() ? 'link' : 'login';

        if ($intent === 'link' && $request->session()->has('impersonator_id')) {
            return redirect('/user/profile')->with('error', 'Привязка входов недоступна в режиме входа под чужим аккаунтом.');
        }

        $botId = (string) config('services.max.bot_id');
        if ($botId === '') {
            return redirect()->route('login')->with('error', 'Вход через MAX временно недоступен.');
        }

        // предыдущая незавершённая попытка этого браузера больше не нужна
        $old = $request->session()->pull(self::SESSION_KEY);
        if ($old) {
            MaxLoginAttempt::where('public_id', $old)->whereIn('status', ['pending', 'awaiting_code'])->update(['status' => 'expired']);
        }

        [$attempt, $secret] = MaxLoginAttempt::start(
            $intent,
            Auth::id(),
            $this->sanitizeReturnTo((string) ($request->query('return') ?: '')),
            $request->ip(),
            $request->userAgent()
        );
        $request->session()->put(self::SESSION_KEY, $attempt->public_id);

        return view('auth.max', [
            'intent'    => $intent,
            'code'      => $attempt->code,
            'link'      => "https://max.ru/{$botId}?start={$secret}",
            'statusUrl' => route('auth.max.status', ['id' => $attempt->public_id]),
            'ttlMin'    => MaxLoginAttempt::TTL_MINUTES,
        ]);
    }

    public function status(Request $request, string $id)
    {
        // статус получает только тот браузер, который начал вход
        if ($request->session()->get(self::SESSION_KEY) !== $id) {
            return response()->json(['state' => 'expired']);
        }

        $attempt = MaxLoginAttempt::where('public_id', $id)->first();
        if (!$attempt) {
            return response()->json(['state' => 'expired']);
        }

        if ($attempt->status === 'approved') {
            return $this->finish($request, $attempt);
        }

        if (in_array($attempt->status, ['pending', 'awaiting_code'], true) && $attempt->isExpired()) {
            $attempt->update(['status' => 'expired']);
            return response()->json(['state' => 'expired']);
        }

        return response()->json(['state' => $attempt->status === 'denied' ? 'denied' : ($attempt->status === 'awaiting_code' ? 'awaiting_code' : ($attempt->status === 'pending' ? 'pending' : 'expired'))]);
    }

    /** Подтверждённая попытка → вход (или привязка). Выполняется один раз (блокировка строки). */
    private function finish(Request $request, MaxLoginAttempt $attempt)
    {
        return DB::transaction(function () use ($request, $attempt) {
            $a = MaxLoginAttempt::where('id', $attempt->id)->lockForUpdate()->first();
            if (!$a || $a->status !== 'approved') {
                return response()->json(['state' => 'expired']);
            }
            // подтверждение «живёт» недолго: код введён, но браузер не забрал вход за TTL — отклоняем
            if ($a->approved_at && $a->approved_at->lt(now()->subMinutes(MaxLoginAttempt::TTL_MINUTES))) {
                $a->update(['status' => 'expired']);
                return response()->json(['state' => 'expired']);
            }

            return $a->intent === 'link' ? $this->finishLink($request, $a) : $this->finishLogin($request, $a);
        });
    }

    private function finishLink(Request $request, MaxLoginAttempt $a)
    {
        $user = $request->user();
        if (!$user || (int) $user->id !== (int) $a->user_id || $request->session()->has('impersonator_id')) {
            $a->update(['status' => 'denied']);
            return response()->json(['state' => 'error', 'message' => 'Сессия изменилась — начните привязку заново.']);
        }

        $mid = (string) $a->max_user_id;
        if (User::where('max_user_id', $mid)->where('id', '!=', $user->id)->exists()
            || AccountLink::isTaken('max', $mid, $user->id)) {
            $a->update(['status' => 'used']);
            return response()->json(['state' => 'error', 'message' => 'Этот MAX уже привязан к другому аккаунту.']);
        }

        $user->max_user_id = $mid;
        if (empty($user->max_chat_id) && $a->max_chat_id) {
            $user->max_chat_id = $a->max_chat_id; // диалог с ботом уже есть — личные уведомления в MAX подключаются заодно
            $user->max_linked_at = now();
            $user->max_notifications_enabled = true;
        }
        $user->save();

        $a->update(['status' => 'used']);
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->flash('status', 'MAX привязан ✅');

        return response()->json(['state' => 'done', 'redirect' => url('/user/profile') . '#providers']);
    }

    private function finishLogin(Request $request, MaxLoginAttempt $a)
    {
        $mid = (string) $a->max_user_id;
        $isNew = false;

        $user = User::where('max_user_id', $mid)->first();

        if (!$user) {
            // после слияния аккаунтов идентификатор мог остаться псевдонимом на другого пользователя
            $user = AccountLink::resolveUser('max', $mid);
        }

        if (!$user && $a->max_chat_id) {
            // уже подключённые личные уведомления в MAX (диалог с ботом) — это тот же человек
            $user = User::where('max_chat_id', $a->max_chat_id)->whereNull('merged_into_user_id')->orderBy('id')->first();
            if ($user && empty($user->max_user_id)) {
                $user->max_user_id = $mid;
                $user->save();
            }
        }

        if (!$user) {
            $isNew = true;

            $user = $this->createOAuthUser('max_user_id', $mid, trim((string) $a->max_name) !== '' ? trim((string) $a->max_name) : 'Пользователь', '', "max_{$mid}@max.local",
                $a->max_chat_id ? ['max_chat_id' => $a->max_chat_id, 'max_linked_at' => now(), 'max_notifications_enabled' => true] : []);
            $isNew = $user->wasRecentlyCreated; // false — пользователя уже создал параллельный запрос
        }

        $a->update(['status' => 'used']);

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put('auth_provider', 'max');
        $request->session()->put('auth_provider_id', $mid);
        $this->authSuccess('max', $isNew);

        $redirect = $isNew ? route('profile.complete') : ($a->return_to ?: url('/events'));
        if ($isNew) {
            $request->session()->flash('welcome', true);
        }

        return response()->json(['state' => 'done', 'redirect' => $redirect]);
    }

    private function sanitizeReturnTo(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);
        if ($urlHost && $appHost && strcasecmp($urlHost, $appHost) !== 0) {
            return null;
        }
        if (str_contains($url, '/auth/') || str_contains($url, '/logout')) {
            return null;
        }

        return $url;
    }
}
