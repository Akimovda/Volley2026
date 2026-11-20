<?php

namespace Tests\Feature;

use App\Models\EventOccurrence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\PgsqlTestCase;

/**
 * Авто увеличение кворума, ЭТАП 3 (docs/auto-quorum.md). Запуск: tools/test-pgsql.sh --filter AutoQuorumTest
 */
class AutoQuorumTest extends PgsqlTestCase
{
    // =====================================================================
    // Шаг A: ёмкость даты = число команд
    // =====================================================================

    private function updateOccurrenceHttp(EventOccurrence $occ, array $fields = [])
    {
        $local = Carbon::parse($occ->starts_at, 'UTC')->setTimezone($occ->timezone ?: 'Europe/Moscow')->format('Y-m-d\TH:i');

        return $this->actingAs($this->organizer())->put("/events/{$occ->event_id}/occurrences/{$occ->id}", array_merge([
            'starts_at_local' => $local,
            'location_id'     => $occ->location_id,
        ], $fields));
    }

    public function test_date_form_teams_count_sets_capacity(): void
    {
        $a = $this->classic42();
        $b = $this->addOccurrence($a, 7);

        $this->updateOccurrenceHttp($a, ['teams_count' => 3, 'subtype' => '4x2'])->assertRedirect()->assertSessionHasNoErrors();

        $a = $a->fresh(['event.gameSettings']);
        $this->assertSame(3, (int) DB::table('event_occurrence_game_settings')->where('occurrence_id', $a->id)->value('teams_count'));
        $this->assertSame(18, (int) $a->max_players, 'max_players даты = размер команды × команд даты');
        $this->assertSame(18, $a->effectiveMaxPlayers());
        $this->assertSame(12, $b->fresh()->effectiveMaxPlayers());

        // запись по ролям — по ёмкости даты
        $this->fillMain($a);
        $this->joinHttp($this->makeUser(), $a, 'outside')->assertOk();
        $this->joinHttp($this->makeUser(), $b, 'outside')->assertOk(); // у b пусто
        $this->assertSame(13, $this->activeRegs($a)->count());

        // вернули команды как у серии — ёмкость снова 12
        $this->updateOccurrenceHttp($a, ['teams_count' => 2, 'subtype' => '4x2'])->assertRedirect();
        $this->assertSame(12, (int) $a->fresh()->max_players);
    }

    public function test_series_edit_recalculates_individually_edited_dates_by_their_teams(): void
    {
        $a  = $this->classic42();
        $b  = $this->addOccurrence($a, 7);
        $c  = $this->addOccurrence($a, 14);
        // b — своё число команд 4 (форма даты), c — отредактирована вручную без своих команд
        $this->updateOccurrenceHttp($b, ['teams_count' => 4, 'subtype' => '4x2'])->assertRedirect();
        $this->updateOccurrenceHttp($c, ['teams_count' => 2, 'subtype' => '4x2', 'min_players' => 8])->assertRedirect();
        $this->assertSame(24, (int) $b->fresh()->max_players);
        $this->assertTrue((bool) $c->fresh()->is_individually_edited);

        $dow = Carbon::parse($a->starts_at, 'UTC')->setTimezone('Europe/Moscow')->dayOfWeekIso;
        $this->updateEventHttp($a, [
            'teams_count' => 3, 'is_recurring' => 1, 'recurrence_type' => 'weekly', 'recurrence_interval' => 1,
            'recurrence_weekdays' => [$dow], 'recurrence_end_type' => 'none',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(18, (int) $a->fresh()->max_players, 'обычная дата — по серии');
        $this->assertSame(24, (int) $b->fresh()->max_players, 'своё число команд сохраняется');
        $this->assertSame(18, (int) $c->fresh()->max_players, 'отредактирована вручную, своих команд нет — по серии');
        $this->assertSame(8, (int) DB::table('event_occurrence_game_settings')->where('occurrence_id', $c->id)->value('min_players'), 'прочие правки даты не тронуты');
    }
}
