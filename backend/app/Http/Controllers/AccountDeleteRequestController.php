<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AccountDeleteRequestController extends Controller
{
    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $userId    = $user->id;
        $userName  = $user->name ?? '—';
        $userEmail = $user->email ?? '—';

        // Отменить активные регистрации на события
        DB::table('event_registrations')
            ->where('user_id', $userId)
            ->whereRaw('is_cancelled IS NULL OR is_cancelled = false')
            ->update(['is_cancelled' => true, 'updated_at' => now()]);

        // Удалить push-токены
        DB::table('device_tokens')->where('user_id', $userId)->delete();

        // Удалить медиа (аватар)
        try {
            $user->clearMediaCollection('avatar');
            $user->clearMediaCollection('photos');
        } catch (\Throwable) {}

        // Удалить Sanctum токены и биометрический токен
        $user->tokens()->delete();

        // Анонимизировать поля (FK-целостность сохраняется через SoftDeletes)
        $user->forceFill([
            'name'              => 'Удалённый пользователь',
            'first_name'        => null,
            'last_name'         => null,
            'patronymic'        => null,
            'email'             => 'deleted_' . $userId . '@deleted.volleyplay.club',
            'phone'             => null,
            'birth_date'        => null,
            'telegram_id'       => null,
            'telegram_username' => null,
            'telegram_phone'    => null,
            'vk_id'             => null,
            'vk_email'          => null,
            'vk_phone'          => null,
            'yandex_id'         => null,
            'yandex_email'      => null,
            'yandex_phone'      => null,
            'yandex_avatar'     => null,
            'apple_id'          => null,
            'max_chat_id'       => null,
            'biometric_token'   => null,
            'profile_photo_path' => null,
            'avatar_media_id'   => null,
            'password'          => bcrypt(Str::random(40)),
        ])->save();

        Log::warning('Account deleted and anonymized', [
            'user_id' => $userId,
            'name'    => $userName,
            'email'   => $userEmail,
        ]);

        // Logout до delete — иначе SoftDeletes скрывает user от Eloquent и Logout event падает
        Auth::logout();

        // SoftDelete после logout
        $user->delete();

        // regenerate (не invalidate) — чтобы flash-сообщение дошло до следующей страницы
        $request->session()->regenerate();

        return redirect('/')->with('success', 'Ваш аккаунт удалён. Спасибо что были с нами.');
    }
}
