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
        [$user, $device] = $this->resolve($request);
        if (!$user) {
            return response()->json(['error' => 'Неверный токен биометрической авторизации.'], 401);
        }

        // один API-токен на устройство: прежние токены этого устройства (и прежней схемы) удаляем, чтобы не копились
        $name = 'biometric:' . $device->id;
        $user->tokens()->whereIn('name', [$name, 'biometric'])->delete();
        $accessToken = $user->createToken($name)->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'user_id'      => $user->id,
        ]);
    }

    public function webLogin(Request $request): JsonResponse
    {
        [$user] = $this->resolve($request);
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
        $userId = $request->user()->id;
        $token = (string) $request->input('biometric_token', '');
        if ($token === '') {
            BiometricDevice::revokeFor($userId);
        } else {
            $ids = BiometricDevice::where('user_id', $userId)->where('token_hash', BiometricDevice::hashToken($token))->pluck('id')->all();
            BiometricDevice::revokeFor($userId, $ids);
        }

        return response()->json(['ok' => true]);
    }

    /** Список устройств пользователя; с biometric_token помечает текущее. */
    public function devices(Request $request): JsonResponse
    {
        $token = (string) $request->input('biometric_token', '');
        $mine = $token !== '' ? BiometricDevice::hashToken($token) : null;

        $list = BiometricDevice::where('user_id', $request->user()->id)
            ->orderByRaw('COALESCE(last_used_at, created_at) DESC')->get()
            ->map(fn ($d) => [
                'id'           => $d->id,
                'label'        => $d->label ?: 'Other',
                'created_at'   => $d->created_at?->toIso8601String(),
                'last_used_at' => $d->last_used_at?->toIso8601String(),
                'current'      => $mine !== null && hash_equals($d->token_hash, $mine),
            ])->values();

        return response()->json(['devices' => $list]);
    }

    public function destroyDevice(Request $request, int $id): JsonResponse
    {
        BiometricDevice::revokeFor($request->user()->id, [$id]);

        return response()->json(['ok' => true]);
    }

    /** @return array{0: ?User, 1: ?BiometricDevice} */
    private function resolve(Request $request): array
    {
        $validated = $request->validate(self::RULES);

        $device = BiometricDevice::where('token_hash', BiometricDevice::hashToken($validated['biometric_token']))->first();
        $user = $device ? User::find($device->user_id) : null; // удалённые (soft delete) не входят
        if (!$user) {
            return [null, null];
        }
        $device->forceFill(['last_used_at' => now()])->save();

        return [$user, $device];
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
