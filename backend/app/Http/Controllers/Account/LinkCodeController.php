<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LinkCodeController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            // кому хотим привязать (необязательно, но удобно)
            'target_provider' => ['nullable', 'in:telegram,vk'],
        ]);

        // генерируем короткий одноразовый код (удобно вводить руками)
        // формат: 8 символов без похожих (0/O/1/I)
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $hash = hash('sha256', $code);

        // живёт 10 минут
        $expiresAt = now()->addMinutes(10);

        // гасим все предыдущие неиспользованные коды этого юзера (чтобы не было каши)
        DB::table('account_link_codes')
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        DB::table('account_link_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => $hash,
            'target_provider' => $data['target_provider'] ?? null,
            'expires_at' => $expiresAt,
            'consumed_at' => null,
            'consumed_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // кладём код в session, чтобы показать на /user/profile (1 раз)
        $request->session()->flash('link_code_plain', $code);
        $request->session()->flash('link_code_expires_at', $expiresAt->format('Y-m-d H:i:s'));
        $request->session()->flash('link_code_target', $data['target_provider'] ?? '');

        return back()->with('status', 'Код для привязки создан.');
    }
}
