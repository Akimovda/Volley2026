<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BiometricController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $token = Str::random(64);
        $request->user()->update(['biometric_token' => $token]);

        return response()->json(['token' => $token]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'biometric_token' => ['required', 'string', 'size:64'],
        ]);

        $user = User::where('biometric_token', $validated['biometric_token'])->first();

        if (!$user) {
            return response()->json(['error' => 'Неверный токен биометрической авторизации.'], 401);
        }

        $accessToken = $user->createToken('biometric')->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'user_id'      => $user->id,
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->user()->update(['biometric_token' => null]);

        return response()->json(['ok' => true]);
    }
}
