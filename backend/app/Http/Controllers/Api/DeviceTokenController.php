<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:ios,android'],
            'token'    => ['required', 'string', 'max:512'],
        ]);

        $userId   = $request->user()->id;
        $token    = $validated['token'];
        $platform = $validated['platform'];

        // Перепривязать токен, если принадлежит другому пользователю
        DeviceToken::where('token', $token)
            ->where('user_id', '!=', $userId)
            ->update(['user_id' => $userId, 'is_active' => true]);

        DeviceToken::updateOrCreate(
            ['token' => $token],
            ['user_id' => $userId, 'platform' => $platform, 'is_active' => true]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->update(['is_active' => false]);

        return response()->json(['ok' => true]);
    }
}
