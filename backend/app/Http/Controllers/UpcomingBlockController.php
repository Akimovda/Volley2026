<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Блок «Скоро»: скрытие мероприятий/блока пользователем и счётчики для организаторов. */
class UpcomingBlockController extends Controller
{
    public const HIDE_DAYS = 30;

    public function dismiss(Request $request): JsonResponse
    {
        $data = $request->validate(['event_id' => ['required', 'integer', 'exists:events,id']]);
        $inserted = DB::table('upcoming_dismissals')->insertOrIgnore([
            'user_id' => $request->user()->id, 'event_id' => $data['event_id'], 'created_at' => now(),
        ]);
        if ($inserted) {
            $this->bump((int) $data['event_id'], 'dismissals');
        }

        return response()->json(['ok' => true]);
    }

    public function undo(Request $request, int $event): JsonResponse
    {
        DB::table('upcoming_dismissals')->where('user_id', $request->user()->id)->where('event_id', $event)->delete();

        return response()->json(['ok' => true]);
    }

    /** «Скрыть блок» на 30 дней. */
    public function hideBlock(Request $request): JsonResponse
    {
        $request->user()->forceFill(['upcoming_hidden_until' => now()->addDays(self::HIDE_DAYS)])->save();

        return response()->json(['ok' => true]);
    }

    /** Переключатель в профиле: показывать блок или нет (нет — до включения обратно). */
    public function setVisible(Request $request): JsonResponse
    {
        $enabled = $request->boolean('enabled');
        $request->user()->forceFill(['upcoming_hidden_until' => $enabled ? null : '2099-01-01 00:00:00'])->save();

        return response()->json(['ok' => true]);
    }

    /** «Показать скрытые мероприятия» в профиле. */
    public function reset(Request $request): JsonResponse
    {
        $n = DB::table('upcoming_dismissals')->where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true, 'restored' => $n]);
    }

    /** Показы/переходы/скрытия (в т.ч. от гостей). Пакет событий от браузера. */
    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events'              => ['required', 'array', 'max:40'],
            'events.*.event_id'   => ['required', 'integer', 'min:1'],
            'events.*.type'       => ['required', 'in:impression,click,dismiss'],
        ]);
        $ids = DB::table('events')->whereIn('id', array_column($data['events'], 'event_id'))->pluck('id')->flip();
        foreach ($data['events'] as $e) {
            if (!isset($ids[$e['event_id']])) {
                continue;
            }
            $this->bump((int) $e['event_id'], ['impression' => 'impressions', 'click' => 'clicks', 'dismiss' => 'dismissals'][$e['type']]);
        }

        return response()->json(['ok' => true]);
    }

    private function bump(int $eventId, string $col): void
    {
        DB::statement(
            "insert into upcoming_block_stats (event_id, day, {$col}) values (?, ?, 1)
             on conflict (event_id, day) do update set {$col} = upcoming_block_stats.{$col} + 1",
            [$eventId, now()->toDateString()]
        );
    }
}
