<?php

namespace App\Http\Middleware;

use App\Models\DeviceToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SavePushToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('push_token') && $request->user()) {
            $token  = (string) $request->query('push_token');
            $userId = $request->user()->id;

            if ($token !== '') {
                DeviceToken::where('token', $token)
                    ->where('user_id', '!=', $userId)
                    ->update(['user_id' => $userId, 'is_active' => true]);

                DeviceToken::updateOrCreate(
                    ['token' => $token],
                    ['user_id' => $userId, 'platform' => 'ios', 'is_active' => true]
                );
            }

            $query = $request->except('push_token');
            $url   = $request->url() . ($query ? '?' . http_build_query($query) : '');

            return redirect($url, 302);
        }

        return $next($request);
    }
}
