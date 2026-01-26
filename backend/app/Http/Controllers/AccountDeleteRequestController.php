<?php

namespace App\Http\Controllers;

use App\Models\AccountDeleteRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccountDeleteRequestController extends Controller
{
    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // не плодим дубликаты "new"
        $existing = AccountDeleteRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'new')
            ->latest('id')
            ->first();

        if ($existing) {
            return back()->with('status', 'Заявка уже отправлена ранее. Администратор рассмотрит её ✅');
        }

        $req = AccountDeleteRequest::create([
            'user_id' => $user->id,
            'status'  => 'new',
            'reason'  => $validated['reason'] ?? null,
        ]);

        Log::warning('Account delete request created', [
            'user_id' => $user->id,
            'request_id' => $req->id,
            'reason' => $req->reason,
        ]);

        // уведомление админу (Telegram/VK) — без фатала, только best-effort
        $notified = $this->notifyAdmins($user, $req);

        if ($notified) {
            $req->forceFill(['notified_at' => now()])->save();
        }

        return back()->with('status', 'Заявка на удаление аккаунта отправлена администратору ✅');
    }

    private function notifyAdmins(User $user, AccountDeleteRequest $req): bool
    {
        $ok = false;

        $name = method_exists($user, 'displayName') ? $user->displayName() : ($user->name ?? '—');
        $phone = $user->phone ?? '—';
        $email = $user->email ?? '—';

        $textPlain =
            "🗑️ Запрос на удаление аккаунта\n"
            ."ID: {$user->id}\n"
            ."Имя: {$name}\n"
            ."Телефон: {$phone}\n"
            ."Email: {$email}\n"
            ."RequestID: {$req->id}\n"
            .(!empty($req->reason) ? ("Причина: ".$req->reason."\n") : "");

        // --- Telegram ---
        $tgToken = config('services.telegram.bot_token');
        $tgChatId = config('services.telegram.admin_chat_id');

        if (!empty($tgToken) && !empty($tgChatId)) {
            try {
                $resp = Http::timeout(5)->asForm()->post("https://api.telegram.org/bot{$tgToken}/sendMessage", [
                    'chat_id' => $tgChatId,
                    'text' => $textPlain,
                ]);

                if ($resp->ok()) {
                    $ok = true;
                } else {
                    Log::warning('Telegram notify failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('Telegram notify exception', ['error' => $e->getMessage()]);
            }
        }

        // --- VK ---
        $vkToken = config('services.vk.admin_token');     // токен группы/сервиса
        $vkPeerId = config('services.vk.admin_peer_id');  // peer_id (юзер/чат)
        if (!empty($vkToken) && !empty($vkPeerId)) {
            try {
                $resp = Http::timeout(5)->asForm()->post('https://api.vk.com/method/messages.send', [
                    'access_token' => $vkToken,
                    'v' => '5.131',
                    'peer_id' => (int) $vkPeerId,
                    'random_id' => random_int(1, PHP_INT_MAX),
                    'message' => $textPlain,
                ]);

                $json = $resp->json();
                if ($resp->ok() && isset($json['response'])) {
                    $ok = true;
                } else {
                    Log::warning('VK notify failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('VK notify exception', ['error' => $e->getMessage()]);
            }
        }

        return $ok;
    }
}
