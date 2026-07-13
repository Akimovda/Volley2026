<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RegistrationEventLogger
{
    /*
    |--------------------------------------------------------------------------
    | LOG
    |--------------------------------------------------------------------------
    | Переиспользует ту же таблицу event_registration_logs, что и существующие
    | registered/cancelled/cancelled_self/restored. registration_id — nullable
    | (2026_07_13_160000): для waitlist-действий (join/leave/auto_booked/
    | removed_by_organizer) регистрации ещё/уже не существует, оставляем null.
    */
    public static function log(
        int $eventId,
        ?int $occurrenceId,
        int $targetUserId,
        ?int $actorId,
        string $action,
        ?int $registrationId = null,
        array $meta = []
    ): void {
        if (!Schema::hasTable('event_registration_logs')) {
            return;
        }

        DB::table('event_registration_logs')->insert([
            'registration_id' => $registrationId,
            'event_id'        => $eventId,
            'occurrence_id'   => $occurrenceId,
            'user_id'         => $targetUserId,
            'actor_id'        => $actorId,
            'action'          => $action,
            'meta'            => !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_at'      => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RESOLVE REAL ACTOR ID
    |--------------------------------------------------------------------------
    | При impersonation ImpersonationController::start() делает
    | Auth::loginUsingId($user->id) — auth()->id() возвращает ПОДМЕНЁННОГО
    | пользователя, реальный админ есть только в session('impersonator_id')
    | (тот же приём, что уже используется в ImpersonationController::leave()
    | с комментарием "Логируем от имени реального админа"). Без этого лог
    | ошибочно припишет действие организатору/игроку, за которого зашёл админ.
    */
    public static function resolveRealActorId(Request $request): ?int
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        if ($impersonatorId) {
            return (int) $impersonatorId;
        }

        return $request->user()?->id;
    }
}
