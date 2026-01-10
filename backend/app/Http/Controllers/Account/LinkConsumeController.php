<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LinkConsumeController extends Controller
{
    /**
     * GET /account/link
     * Страница "Ввести код" (вход вторым способом).
     */
    public function show(Request $request)
    {
        /** @var User $u */
        $u = $request->user();

        // История привязок (если таблица есть)
        $audits = [];
        if (DB::getSchemaBuilder()->hasTable('account_link_audits')) {
            $audits = DB::table('account_link_audits')
                ->where('user_id', $u->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        return view('account.link', [
            'audits' => $audits,
            'provider' => session('auth_provider'), // 'vk' | 'telegram' | null
        ]);
    }

    /**
     * POST /account/link
     * Вводим одноразовый код и привязываем текущий вход (TG/VK) к аккаунту-владельцу кода.
     *
     * Важно: делаем именно "перенос" провайдерных полей:
     * - owner получает TG/VK из current
     * - у current эти поля очищаются (иначе словим UNIQUE на users.telegram_id / users.vk_id)
     */
    public function store(Request $request)
    {
        // ===== [AUTH] =====
        /** @var User $current */
        $current = $request->user();

        // ===== [VALIDATION] =====
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:32'],
        ], [
            'code.required' => 'Введите код.',
        ]);

        $plain = trim($validated['code']);
        $hash  = hash('sha256', $plain);

        // ===== [SESSION] текущий провайдер (для подсказок/валидации) =====
        $provider = session('auth_provider'); // 'vk' | 'telegram' | null

        // ===== [LOAD CODE] =====
        $code = DB::table('account_link_codes')
            ->where('code_hash', $hash)
            ->first();

        if (!$code) {
            return back()->withErrors(['code' => 'Код не найден или уже использован.'])->withInput();
        }

        // ===== [CHECKS] срок/использование =====
        if (!empty($code->consumed_at)) {
            return back()->withErrors(['code' => 'Код уже использован.'])->withInput();
        }

        if (!empty($code->expires_at) && now()->greaterThan($code->expires_at)) {
            return back()->withErrors(['code' => 'Код истёк. Сгенерируйте новый.'])->withInput();
        }

        // ===== [GUARD] Сам себе =====
        if ((int) $code->user_id === (int) $current->id) {
            return back()->withErrors([
                'code' => "Нельзя привязать аккаунт к самому себе.\n" .
                    "Этот код нужно вводить во ВТОРОМ аккаунте:\n" .
                    "1) Скопируйте код\n" .
                    "2) Выйдите из текущего аккаунта\n" .
                    "3) Войдите вторым способом (Telegram/VK)\n" .
                    "4) Введите код на этой странице",
            ])->withInput();
        }

        // ===== [OPTIONAL] Код под конкретного провайдера =====
        // target_provider = 'telegram' | 'vk' | null
        if (!empty($code->target_provider) && $provider && $code->target_provider !== $provider) {
            return back()->withErrors([
                'code' => 'Этот код предназначен для входа через ' .
                    ($code->target_provider === 'telegram' ? 'Telegram' : 'VK') .
                    '. Перезайдите этим способом и введите код.',
            ])->withInput();
        }

        /** @var User $owner */
        $owner = User::findOrFail($code->user_id);

        // ===== [DETECT] что переносим из current в owner =====
        $currentHasTg = !empty($current->telegram_id);
        $currentHasVk = !empty($current->vk_id);

        if (!$currentHasTg && !$currentHasVk) {
            return back()->withErrors([
                'code' => 'Не удалось определить Telegram/VK у текущего входа. Перезайдите через Telegram или VK и повторите.',
            ])->withInput();
        }

        // ===== [SAFETY] защита от перепривязки (не перезатираем чужое) =====
        // Идемпотентно разрешаем только "то же самое значение".
        if ($currentHasTg && !empty($owner->telegram_id) && (string) $owner->telegram_id !== (string) $current->telegram_id) {
            return back()->withErrors([
                'code' => 'У аккаунта-владельца кода уже привязан другой Telegram. Перепривязка запрещена.',
            ])->withInput();
        }
        if ($currentHasVk && !empty($owner->vk_id) && (string) $owner->vk_id !== (string) $current->vk_id) {
            return back()->withErrors([
                'code' => 'У аккаунта-владельца кода уже привязан другой VK. Перепривязка запрещена.',
            ])->withInput();
        }

        try {
            DB::transaction(function () use ($request, $code, $owner, $current, $provider, $currentHasTg, $currentHasVk) {
                // ---- 1) Уникальность провайдера: один TG/VK не может быть у двух user ----
                if ($currentHasTg) {
                    $busy = User::where('telegram_id', $current->telegram_id)
                        ->where('id', '!=', $owner->id)
                        ->exists();
                    if ($busy) {
                        abort(409, 'Этот Telegram уже привязан к другому аккаунту.');
                    }
                }
                if ($currentHasVk) {
                    $busy = User::where('vk_id', $current->vk_id)
                        ->where('id', '!=', $owner->id)
                        ->exists();
                    if ($busy) {
                        abort(409, 'Этот VK уже привязан к другому аккаунту.');
                    }
                }

                // ---- 2) ПЕРЕНОС: owner <= current ----
                if ($currentHasTg) {
                    $owner->telegram_id = $current->telegram_id;
                    $owner->telegram_username = $current->telegram_username ?: $owner->telegram_username;
                }
                if ($currentHasVk) {
                    $owner->vk_id = $current->vk_id;
                    $owner->vk_email = $current->vk_email ?: $owner->vk_email;
                }
                $owner->save();

                // ---- 3) ПЕРЕНОС: current очищаем (иначе UNIQUE constraint) ----
                if ($currentHasTg) {
                    $current->telegram_id = null;
                    $current->telegram_username = null;
                }
                if ($currentHasVk) {
                    $current->vk_id = null;
                    $current->vk_email = null;
                }
                $current->save();

                // ---- 4) Код использован ----
                DB::table('account_link_codes')
                    ->where('id', $code->id)
                    ->update([
                        'consumed_at' => now(),
                        'consumed_by_user_id' => $current->id,
                        'updated_at' => now(),
                    ]);

                // ---- 5) Audit (история) ----
                if (DB::getSchemaBuilder()->hasTable('account_link_audits')) {
                    DB::table('account_link_audits')->insert([
                        'user_id' => $owner->id,
                        'linked_from_user_id' => $current->id,
                        'provider' => $provider ?: ($currentHasTg ? 'telegram' : 'vk'),
                        'provider_user_id' => $currentHasTg ? (string) $owner->telegram_id : (string) $owner->vk_id,
                        'method' => 'link_code',
                        'link_code_id' => $code->id,
                        'ip' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 255),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // abort(409, ...) прилетает сюда
            throw $e;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // На всякий случай: если где-то всё же словили UNIQUE — возвращаем 409
            abort(409, 'Этот Telegram/VK уже привязан к другому аккаунту.');
        }

        return redirect('/user/profile')->with('status', 'Аккаунты успешно привязаны ✅');
    }
}
