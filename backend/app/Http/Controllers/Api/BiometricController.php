<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BiometricController extends Controller
{
    private const RULES = ['biometric_token' => ['required', 'string', 'min:16', 'max:128']];

    /** Включает Face ID на этом устройстве: токен у каждого устройства свой, хранится хеш. */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate(self::RULES);
        $user = $request->user();

        // токен генерирует устройство; если он уже числится за другим аккаунтом — теперь он принадлежит тому, кто вошёл
        BiometricDevice::updateOrCreate(
            ['token_hash' => BiometricDevice::hashToken($validated['biometric_token'])],
            ['user_id' => $user->id, 'label' => $this->deviceLabel($request)]
        );

        // не больше MAX_PER_USER устройств: самые старые по использованию отключаются
        $stale = BiometricDevice::where('user_id', $user->id)
            ->orderByRaw('COALESCE(last_used_at, created_at) DESC')
            ->skip(BiometricDevice::MAX_PER_USER)->take(100)->pluck('id');
        if ($stale->isNotEmpty()) {
            BiometricDevice::whereIn('id', $stale)->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function login(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['error' => 'Неверный токен биометрической авторизации.'], 401);
        }

        $accessToken = $user->createToken('biometric')->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'user_id'      => $user->id,
        ]);
    }

    public function webLogin(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['error' => 'Неверный токен биометрической авторизации.'], 401);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'redirect' => '/events']);
    }

    /** С biometric_token — отключает только это устройство, без него — все устройства пользователя. */
    public function revoke(Request $request): JsonResponse
    {
        $query = BiometricDevice::where('user_id', $request->user()->id);
        $token = (string) $request->input('biometric_token', '');
        if ($token !== '') {
            $query->where('token_hash', BiometricDevice::hashToken($token));
        }
        $query->delete();

        return response()->json(['ok' => true]);
    }

    private function resolveUser(Request $request): ?User
    {
        $validated = $request->validate(self::RULES);

        $device = BiometricDevice::where('token_hash', BiometricDevice::hashToken($validated['biometric_token']))->first();
        if (!$device) {
            return null;
        }
        $user = User::find($device->user_id); // удалённые (soft delete) не входят
        if (!$user) {
            return null;
        }
        $device->forceFill(['last_used_at' => now()])->save();

        return $user;
    }

    private function deviceLabel(Request $request): string
    {
        $ua = (string) $request->userAgent();
        return match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad')   => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            default                     => 'Other',
        };
    }
}
