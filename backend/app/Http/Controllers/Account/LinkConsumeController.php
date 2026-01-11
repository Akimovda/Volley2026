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

    public function store(Request $request)
    {
        /** @var User $current */
        $current = $request->user();

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:32'],
        ], [
            'code.required' => 'Введите код.',
        ]);

        $plain = trim($validated['code']);
        $hash  = hash('sha256', $plain);

        // текущий провайдер (для подсказок/валидации)
        $provider = session('auth_provider'); // 'vk' | 'telegram' | null

        $code = DB::table('account_link_codes')
            ->where('code_hash', $hash)
            ->first();

        if (!$code) {
            return back()->withErrors(['code' => 'Код не найден или уже использован.'])->withInput();
        }

        if (!empty($code->consumed_at)) {
            return back()->withErrors(['code' => 'Код уже использован.'])->withInput();
        }

        if (!empty($code->expires_at) && now()->greaterThan($code->expires_at)) {
            return back()->withErrors(['code' => 'Код истёк. Сгенерируйте новый.'])->withInput();
        }

        // Сам себе
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

        // Код под конкретного провайдера
        if (!empty($code->target_provider) && $provider && $code->target_provider !== $provider) {
            return back()->withErrors([
                'code' => 'Этот код предназначен для входа через ' .
                    ($code->target_provider === 'telegram' ? 'Telegram' : 'VK') .
                    '. Перезайдите этим способом и введите код.',
            ])->withInput();
        }

        /** @var User $owner */
        $owner = User::findOrFail($code->user_id);

        // что переносим из current в owner
        $currentHasTg = !empty($current->telegram_id);
        $currentHasVk = !empty($current->vk_id);

        if (!$currentHasTg && !$currentHasVk) {
            return back()->withErrors([
                'code' => 'Не удалось определить Telegram/VK у текущего входа. Перезайдите через Telegram или VK и повторите.',
            ])->withInput();
        }

        // защита от перепривязки (не перезатираем чужое)
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
            DB::transaction(function () use ($request, $code, $provider, $currentHasTg, $currentHasVk, $owner, $current) {

                // Лочим строки, чтобы не словить гонки
                /** @var User $ownerLocked */
                $ownerLocked = User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();

                /** @var User $currentLocked */
                $currentLocked = User::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();

                $tgId = $currentHasTg ? (string) $currentLocked->telegram_id : null;
                $tgUsername = $currentHasTg ? (string) ($currentLocked->telegram_username ?? '') : null;

                $vkId = $currentHasVk ? (string) $currentLocked->vk_id : null;
                $vkEmail = $currentHasVk ? (string) ($currentLocked->vk_email ?? '') : null;

                // 1) Уникальность провайдера:
                //    исключаем И owner, И current (иначе current сам себя "занимает" -> 409)
                if ($currentHasTg && $tgId !== null && $tgId !== '') {
                    $busy = User::query()
                        ->where('telegram_id', $tgId)
                        ->whereNotIn('id', [(int) $ownerLocked->id, (int) $currentLocked->id])
                        ->exists();

                    if ($busy) {
                        abort(409, 'Этот Telegram уже привязан к другому аккаунту.');
                    }
                }

                if ($currentHasVk && $vkId !== null && $vkId !== '') {
                    $busy = User::query()
                        ->where('vk_id', $vkId)
                        ->whereNotIn('id', [(int) $ownerLocked->id, (int) $currentLocked->id])
                        ->exists();

                    if ($busy) {
                        abort(409, 'Этот VK уже привязан к другому аккаунту.');
                    }
                }

                // 2) ПЕРЕНОС (без падений UNIQUE):
                //    сначала очищаем current, потом выставляем owner
                if ($currentHasTg) {
                    $currentLocked->telegram_id = null;
                    $currentLocked->telegram_username = null;
                }
                if ($currentHasVk) {
                    $currentLocked->vk_id = null;
                    $currentLocked->vk_email = null;
                }
                $currentLocked->save();

                if ($currentHasTg && $tgId) {
                    $ownerLocked->telegram_id = $tgId;
                    $ownerLocked->telegram_username = $tgUsername ?: $ownerLocked->telegram_username;
                }
                if ($currentHasVk && $vkId) {
                    $ownerLocked->vk_id = $vkId;
                    $ownerLocked->vk_email = $vkEmail ?: $ownerLocked->vk_email;
                }
                $ownerLocked->save();

                // 3) Код использован
                DB::table('account_link_codes')
                    ->where('id', $code->id)
                    ->update([
                        'consumed_at' => now(),
                        'consumed_by_user_id' => $currentLocked->id,
                        'updated_at' => now(),
                    ]);

                // 4) Audit (история)
                if (DB::getSchemaBuilder()->hasTable('account_link_audits')) {
                    $resolvedProvider = $provider ?: ($currentHasTg ? 'telegram' : 'vk');

                    $providerUserId =
                        $resolvedProvider === 'telegram'
                            ? (string) ($ownerLocked->telegram_id ?? '')
                            : (string) ($ownerLocked->vk_id ?? '');

                    DB::table('account_link_audits')->insert([
                        'user_id' => $ownerLocked->id,
                        'linked_from_user_id' => $currentLocked->id,
                        'provider' => $resolvedProvider,
                        'provider_user_id' => $providerUserId,
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
            throw $e;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            abort(409, 'Этот Telegram/VK уже привязан к другому аккаунту.');
        }

        return redirect('/user/profile')->with('status', 'Аккаунты успешно привязаны ✅');
    }
}
