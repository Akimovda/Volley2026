<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminAuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserRestrictionController extends Controller
{
    /**
     * Ограничение записи на мероприятия (events):
     * - mode=forever => ends_at = null
     * - mode=until   => ends_at = дата
     * - event_ids    => список ID мероприятий
     *
     * Доп. требование:
     * ✅ сразу удалить пользователя из записей на эти мероприятия
     * (чтобы он исчез из "уже записан" прямо после блокировки).
     */
    public function banEvents(Request $request, User $user)
    {
        // --- validate
        $data = $request->validate([
            'mode'      => ['required', 'in:forever,until'],
            'until'     => ['nullable', 'date'],
            'event_ids' => ['required', 'string', 'max:2000'],
            'reason'    => ['nullable', 'string', 'max:1000'],
        ]);

        // --- parse event_ids
        $eventIds = $this->parseEventIds((string) $data['event_ids']);
        if (empty($eventIds)) {
            return back()->with('error', 'Укажи хотя бы один event_id (числа через запятую/пробел).');
        }

        // --- parse ends_at
        $endsAt = $this->parseEndsAt((string) $data['mode'], $data['until'] ?? null);
        if ($data['mode'] === 'until' && $endsAt === null) {
            return back()->with('error', 'Укажи дату окончания блокировки.');
        }

        DB::transaction(function () use ($request, $user, $endsAt, $data, $eventIds) {
            $now = Carbon::now();

            // 1) Держим одно активное events-ограничение (удобно для UI/логики)
            DB::table('user_restrictions')
                ->where('user_id', $user->id)
                ->where('scope', 'events')
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                })
                ->delete();

            // 2) Вставляем текущее ограничение
            DB::table('user_restrictions')->insert([
                'user_id'    => $user->id,
                'scope'      => 'events',
                'ends_at'    => $endsAt, // null = forever
                'event_ids'  => json_encode($eventIds, JSON_UNESCAPED_UNICODE),
                'reason'     => $data['reason'] ?? null,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3) ✅ Авто-удаление из записей на заблокированные мероприятия
            $deletedRegs = $this->dropUserRegistrationsForEvents((int) $user->id, $eventIds);

            // 4) Audit
            AdminAuditLogger::log(
                action: 'user.restriction.set',
                targetType: 'user',
                targetId: (string) $user->id,
                meta: [
                    'scope'                 => 'events',
                    'ends_at'               => $endsAt?->toISOString(),
                    'event_ids'             => $eventIds,
                    'deleted_registrations' => $deletedRegs,
                ],
                note: 'Admin set events restriction',
                request: $request,
            );
        });

        return back()->with('status', 'Ограничение (events) установлено ✅');
    }

    /**
     * Снять ВСЕ активные ограничения (events).
     * Требует confirm=yes.
     */
    public function clearAll(Request $request, User $user)
    {
        $request->validate([
            'confirm' => ['required', 'in:yes'],
        ], [
            'confirm.in' => 'Подтверждение не пройдено. Нужно ввести yes.',
        ]);

        $now = Carbon::now();

        DB::transaction(function () use ($request, $user, $now) {
            $deleted = DB::table('user_restrictions')
                ->where('user_id', $user->id)
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                })
                ->delete();

            AdminAuditLogger::log(
                action: 'user.restriction.clear',
                targetType: 'user',
                targetId: (string) $user->id,
                meta: ['deleted_count' => $deleted],
                note: 'Admin cleared all active restrictions',
                request: $request,
            );
        });

        return back()->with('status', 'Все активные ограничения сняты ✅');
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * mode=forever => null
     * mode=until   => Carbon::parse($until) (или null при ошибке)
     */
    private function parseEndsAt(string $mode, ?string $until): ?Carbon
    {
        if ($mode !== 'until') {
            return null;
        }
        if (empty($until)) {
            return null;
        }
        try {
            return Carbon::parse($until);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * "1, 2  3" => [1,2,3]
     */
    private function parseEventIds(string $raw): array
    {
        $parts = preg_split('/[,\s]+/', trim($raw)) ?: [];
        $ids = [];

        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '' && ctype_digit($p)) {
                $ids[] = (int) $p;
            }
        }

        // уникальные, >0
        return array_values(array_unique(array_filter($ids, fn ($v) => $v > 0)));
    }

    /**
     * ✅ Удаляем записи пользователя на указанные event_id.
     * Возвращаем сколько строк удалили (для аудита).
     */
    private function dropUserRegistrationsForEvents(int $userId, array $eventIds): int
    {
        $eventIds = array_values(array_unique(array_map('intval', $eventIds)));
        if (empty($eventIds)) {
            return 0;
        }

        return (int) DB::table('event_registrations')
            ->where('user_id', $userId)
            ->whereIn('event_id', $eventIds)
            ->delete();
    }
}
