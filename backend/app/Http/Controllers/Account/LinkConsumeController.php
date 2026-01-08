<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LinkConsumeController extends Controller
{
    public function show(Request $request)
    {
        return view('account.link');
    }

    public function store(Request $request)
    {
        $user = $request->user(); // тот, кто сейчас вошёл (например, через TG)
        $data = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:32'],
        ]);

        $code = strtoupper(trim($data['code']));
        $hash = hash('sha256', $code);

        return DB::transaction(function () use ($request, $user, $hash) {

            // блокируем строку кода на время обработки
            $row = DB::table('account_link_codes')
                ->where('code_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return back()->withErrors(['code' => 'Код не найден.'])->withInput();
            }

            if (!is_null($row->consumed_at)) {
                return back()->withErrors(['code' => 'Код уже использован.'])->withInput();
            }

            if (now()->greaterThan($row->expires_at)) {
                return back()->withErrors(['code' => 'Код истёк. Сгенерируйте новый.'])->withInput();
            }

            // владелец кода — "главный" аккаунт, к которому привязываемся
            $ownerUserId = (int) $row->user_id;

            if ($ownerUserId === (int) $user->id) {
                return back()->withErrors(['code' => 'Нельзя привязать аккаунт к самому себе.'])->withInput();
            }

            // определяем текущего провайдера входа (мы добавим это в TG/VK контроллеры)
            $provider = (string) $request->session()->get('auth_provider', '');
            if (!in_array($provider, ['telegram', 'vk'], true)) {
                return back()->withErrors(['code' => 'Не удалось определить провайдера входа (telegram/vk).'])->withInput();
            }

            // вытаскиваем provider_user_id из текущего пользователя
            // telegram => users.telegram_id, vk => users.vk_id
            $providerUserId = null;
            if ($provider === 'telegram') {
                $providerUserId = $user->telegram_id;
            } elseif ($provider === 'vk') {
                $providerUserId = $user->vk_id;
            }

            if (empty($providerUserId)) {
                return back()->withErrors(['code' => 'В текущем аккаунте нет данных провайдера (telegram_id/vk_id).'])->withInput();
            }

            // защита: если этот provider_user_id уже привязан к кому-то — не даём дубль
            $exists = DB::table('account_links')
                ->where('provider', $provider)
                ->where('provider_user_id', (string) $providerUserId)
                ->exists();

            if ($exists) {
                return back()->withErrors(['code' => 'Этот провайдер уже привязан к другому аккаунту.'])->withInput();
            }

            // создаём привязку
            DB::table('account_links')->insert([
                'user_id' => $ownerUserId,
                'provider' => $provider,
                'provider_user_id' => (string) $providerUserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // помечаем код использованным
            DB::table('account_link_codes')
                ->where('id', $row->id)
                ->update([
                    'consumed_at' => now(),
                    'consumed_by_user_id' => $user->id,
                    'updated_at' => now(),
                ]);

            return redirect('/user/profile')->with('status', 'Аккаунт успешно привязан.');
        });
    }
}
