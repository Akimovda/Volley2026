<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TmaAuthController extends Controller
{
    // Клиент в Mini App опрашивает этот endpoint после открытия OAuth в браузере.
    // Когда callback сохранил token в кеше — возвращаем {ready:true, token:...}.
    public function status(Request $request)
    {
        $clientId = (string) $request->query('client_id', '');
        if ($clientId === '') {
            return response()->json(['ready' => false]);
        }

        $pending = Cache::get("tma_pending_{$clientId}");
        if (!$pending || empty($pending['token'])) {
            return response()->json(['ready' => false]);
        }

        return response()->json(['ready' => true, 'token' => $pending['token']]);
    }

    // Mini App обменивает one-time token на сессию.
    public function exchange(Request $request)
    {
        $token = (string) $request->input('token', '');
        if ($token === '') {
            return response()->json(['ok' => false, 'error' => 'Missing token'], 400);
        }

        $data = Cache::pull("tma_auth_token_{$token}");
        if (!$data || empty($data['user_id'])) {
            return response()->json(['ok' => false, 'error' => 'Invalid or expired token'], 422);
        }

        $user = User::find($data['user_id']);
        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'User not found'], 422);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok'       => true,
            'redirect' => $data['redirect'] ?? url('/events'),
        ]);
    }
}
