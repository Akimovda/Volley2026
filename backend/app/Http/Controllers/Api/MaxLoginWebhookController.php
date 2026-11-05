<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaxLoginAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Эндпоинты для бота MAX (общий секрет X-Bind-Secret, как у остальных интеграций): шаги входа через MAX.
 * Тексты ответов отдаёт сервер — бот только пересылает их пользователю.
 */
class MaxLoginWebhookController extends Controller
{
    protected function assertSecret(Request $request): void
    {
        $incoming = (string) $request->header('X-Bind-Secret', '');
        $expected = (string) config('services.bind.secret');

        abort_if($expected === '', 500, 'BIND_WEBHOOK_SECRET is not configured.');
        abort_if(!hash_equals($expected, $incoming), 403, 'Invalid secret.');
    }

    /** «Старт» с токеном входа: запоминаем, кто нажал, и просим ввести код с сайта. */
    public function begin(Request $request)
    {
        $this->assertSecret($request);

        $data = $request->validate([
            'token'       => ['required', 'string', 'max:64'],
            'max_user_id' => ['required', 'string', 'max:64'],
            'chat_id'     => ['nullable', 'string', 'max:64'],
            'name'        => ['nullable', 'string', 'max:191'],
            'username'    => ['nullable', 'string', 'max:191'],
        ]);

        // защита от «спама» попытками с одного пользователя MAX
        $rlKey = 'max_login_begin_' . $data['max_user_id'];
        if ((int) Cache::get($rlKey, 0) >= 10) {
            return response()->json(['ok' => false, 'message' => 'Слишком много попыток входа. Подождите час и попробуйте снова.']);
        }
        Cache::put($rlKey, (int) Cache::get($rlKey, 0) + 1, now()->addHour());

        $attempt = MaxLoginAttempt::findBySecret($data['token']);
        if (!$attempt || $attempt->status !== 'pending' || $attempt->isExpired()) {
            return response()->json([
                'ok' => false,
                'message' => 'Ссылка для входа устарела или уже использована. 📲 Вернитесь в VolleyClub и нажмите «Войти через MAX» ещё раз.',
            ]);
        }

        $attempt->update([
            'status'       => 'awaiting_code',
            'max_user_id'  => $data['max_user_id'],
            'max_chat_id'  => $data['chat_id'] ?? null,
            'max_name'     => $data['name'] ?? null,
            'max_username' => $data['username'] ?? null,
            'attempts'     => 0,
        ]);

        $what = $attempt->intent === 'link' ? 'Привязка MAX к вашему аккаунту VolleyClub' : 'Вход на VolleyClub';

        return response()->json([
            'ok' => true,
            'message' => "{$what}.\n\nВведите в этот чат 4-значный код, который показан в VolleyClub.\n\nЕсли вы не начинали вход в VolleyClub — просто проигнорируйте это сообщение.",
        ]);
    }

    /** Сообщение из 4 цифр от пользователя: проверяем код ожидающей попытки. Нет такой попытки — бот молчит (ignore). */
    public function code(Request $request)
    {
        $this->assertSecret($request);

        $data = $request->validate([
            'max_user_id' => ['required', 'string', 'max:64'],
            'text'        => ['required', 'string', 'max:64'],
        ]);

        $digits = trim($data['text']);
        if (!preg_match('/^\d{4}$/', $digits)) {
            return response()->json(['ok' => false, 'ignore' => true]);
        }

        $attempt = MaxLoginAttempt::where('max_user_id', $data['max_user_id'])
            ->where('status', 'awaiting_code')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$attempt) {
            return response()->json(['ok' => false, 'ignore' => true]);
        }

        if (hash_equals((string) $attempt->code, $digits)) {
            $attempt->update(['status' => 'approved', 'approved_at' => now()]);

            return response()->json(['ok' => true, 'message' => '✅ Готово!👌 📲 Вернитесь в VolleyClub — вход выполнится автоматически.']);
        }

        $attempt->increment('attempts');
        if ($attempt->attempts >= MaxLoginAttempt::MAX_CODE_TRIES) {
            $attempt->update(['status' => 'denied']);

            return response()->json(['ok' => false, 'message' => 'Слишком много неверных попыток. 📲 Вернитесь в VolleyClub и начните вход заново.']);
        }

        return response()->json([
            'ok' => false,
            'message' => 'Неверный код. Осталось попыток: ' . (MaxLoginAttempt::MAX_CODE_TRIES - $attempt->attempts) . '.',
        ]);
    }
}
