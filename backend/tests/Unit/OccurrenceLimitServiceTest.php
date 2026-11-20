<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventGameSetting;
use App\Models\EventOccurrence;
use App\Models\EventOccurrenceGameSetting;
use App\Models\EventRoleSlot;
use App\Services\OccurrenceLimitService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * OccurrenceLimitService без БД (модели в памяти, связи через setRelation). docs/auto-quorum.md, этап 2.
 */
class OccurrenceLimitServiceTest extends TestCase
{
    private OccurrenceLimitService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new OccurrenceLimitService();
    }

    /** Дата с настройками мероприятия; $slots — role => max_slots. */
    private function occ(string $direction, array $gs, array $slots, ?int $quorum = null, ?int $occMax = null, array $override = []): array
    {
        $event = new Event();
        $event->forceFill(['id' => 1, 'direction' => $direction, 'format' => 'game']);
        $settings = new EventGameSetting();
        $settings->forceFill(array_merge(['event_id' => 1, 'teams_count' => 2, 'max_players' => 12, 'reserve_players_max' => null], $gs));
        $event->setRelation('gameSettings', $settings);

        $occ = new EventOccurrence();
        $occ->forceFill(['id' => 10, 'event_id' => 1, 'quorum_teams_count' => $quorum, 'max_players' => $occMax]);
        $occ->setRelation('event', $event);
        $ov = null;
        if ($override) {
            $ov = new EventOccurrenceGameSetting();
            $ov->forceFill($override);
        }
        $occ->setRelation('gameSettingsOverride', $ov);

        $coll = new Collection();
        foreach ($slots as $role => $max) {
            $s = new EventRoleSlot();
            $s->forceFill(['event_id' => 1, 'role' => $role, 'max_slots' => $max]);
            $coll->push($s);
        }

        return [$occ, $coll];
    }

    public function test_not_expanded_returns_current_values(): void
    {
        // max_slots намеренно «нестандартные» — не расширенная дата обязана вернуть их как есть
        [$o, $slots] = $this->occ('classic', ['subtype' => '4x2', 'max_players' => 12], ['setter' => 5, 'outside' => 7]);
        $this->assertFalse($this->svc->isExpanded($o));
        $this->assertSame(['setter' => 5, 'outside' => 7], $this->svc->roleLimits($o, $slots));
        $this->assertSame(12, $this->svc->maxPlayers($o));
        $this->assertSame(12, $this->svc->seriesMaxPlayers($o));
        $this->assertSame(2, $this->svc->teamsFor($o));
    }

    public function test_not_expanded_occurrence_max_players_override(): void
    {
        [$o] = $this->occ('classic', ['subtype' => '4x2', 'max_players' => 12], [], null, 18);
        $this->assertSame(18, $this->svc->maxPlayers($o), 'как effectiveMaxPlayers(): max_players даты в приоритете');
        $this->assertSame(12, $this->svc->seriesMaxPlayers($o), 'уровень мероприятия — как раньше у анонса');
    }

    public function test_teams_for_uses_override_then_event(): void
    {
        [$o] = $this->occ('classic', ['subtype' => '4x2'], [], null, null, ['teams_count' => 4]);
        $this->assertSame(4, $this->svc->teamsFor($o));
        $this->assertSame(2, $this->svc->baseTeams($o));
        [$o] = $this->occ('classic', ['subtype' => '4x2'], [], 3, null, ['teams_count' => 4]);
        $this->assertSame(3, $this->svc->teamsFor($o), 'quorum_teams_count важнее переопределения');
    }

    public function test_classic_4x2_expanded(): void
    {
        [$o, $slots] = $this->occ('classic', ['subtype' => '4x2'], ['setter' => 4, 'outside' => 8], 3);
        $this->assertTrue($this->svc->isExpanded($o));
        $this->assertSame(['setter' => 2, 'outside' => 4], $this->svc->perTeamRoles($o));
        $this->assertSame(6, $this->svc->teamSize($o));
        $this->assertSame(['setter' => 6, 'outside' => 12], $this->svc->roleLimits($o, $slots));
        $this->assertSame(12, $this->svc->roleLimit($o, 'outside', $slots));
        $this->assertSame(18, $this->svc->maxPlayers($o));
        $this->assertSame(18, $this->svc->seriesMaxPlayers($o));
    }

    public function test_classic_5x1_with_and_without_libero_expanded(): void
    {
        [$o, $slots] = $this->occ('classic', ['subtype' => '5x1', 'libero_mode' => 'without_libero'],
            ['setter' => 2, 'outside' => 4, 'opposite' => 2, 'middle' => 4], 3);
        $this->assertSame(['setter' => 3, 'outside' => 6, 'opposite' => 3, 'middle' => 6], $this->svc->roleLimits($o, $slots));
        $this->assertSame(18, $this->svc->maxPlayers($o));

        [$o, $slots] = $this->occ('classic', ['subtype' => '5x1', 'libero_mode' => 'with_libero'],
            ['setter' => 2, 'outside' => 4, 'opposite' => 2, 'middle' => 4, 'libero' => 2], 3);
        $this->assertSame(3, $this->svc->roleLimit($o, 'libero', $slots));
        $this->assertSame(21, $this->svc->maxPlayers($o));
    }

    public function test_classic_without_positions_expanded(): void
    {
        [$o, $slots] = $this->occ('classic', ['subtype' => '4x2', 'registration_without_positions' => true], ['player' => 12], 3);
        $this->assertSame(['player' => 6], $this->svc->perTeamRoles($o));
        $this->assertSame(['player' => 18], $this->svc->roleLimits($o, $slots));
        $this->assertSame(18, $this->svc->maxPlayers($o));
    }

    public function test_beach_plain_and_by_roles_expanded(): void
    {
        [$o, $slots] = $this->occ('beach', ['subtype' => '2x2', 'max_players' => 4], ['player' => 4], 3);
        $this->assertSame(['player' => 6], $this->svc->roleLimits($o, $slots));
        $this->assertSame(6, $this->svc->maxPlayers($o));

        [$o, $slots] = $this->occ('beach', ['subtype' => '2x2', 'max_players' => 4, 'registration_by_roles' => true], ['blocker' => 2, 'defender' => 2], 3);
        $this->assertSame(['blocker' => 1, 'defender' => 1], $this->svc->perTeamRoles($o));
        $this->assertSame(['blocker' => 3, 'defender' => 3], $this->svc->roleLimits($o, $slots));
        $this->assertSame(6, $this->svc->maxPlayers($o));
    }

    public function test_beach_4x4_uses_beach_scheme(): void
    {
        [$o, $slots] = $this->occ('beach', ['subtype' => '4x4', 'max_players' => 8], ['player' => 8], 3);
        $this->assertSame(['player' => 4], $this->svc->perTeamRoles($o), 'пляжная 4×4 — одна роль player, не классика');
        $this->assertSame(12, $this->svc->maxPlayers($o));
    }

    public function test_unknown_role_and_reserve_slot_keep_max_slots_when_expanded(): void
    {
        [$o, $slots] = $this->occ('classic', ['subtype' => '4x2'], ['setter' => 4, 'outside' => 8, 'reserve' => 2, 'middle' => 1], 3);
        $limits = $this->svc->roleLimits($o, $slots);
        $this->assertSame(2, $limits['reserve']);
        $this->assertSame(1, $limits['middle'], 'роли нет в схеме 4x2 — max_slots без изменений');
        $this->assertSame(0, $this->svc->roleLimit($o, 'libero', $slots), 'роли нет у мероприятия');
    }

    public function test_reserve_max_not_scaled(): void
    {
        [$o] = $this->occ('classic', ['subtype' => '4x2', 'reserve_players_max' => 2], [], 3);
        $this->assertSame(2, $this->svc->reserveMax($o));
    }

    public function test_quorum_zero_is_not_expanded(): void
    {
        [$o, $slots] = $this->occ('classic', ['subtype' => '4x2'], ['setter' => 4, 'outside' => 8], 0);
        $this->assertFalse($this->svc->isExpanded($o));
        $this->assertSame(12, $this->svc->maxPlayers($o));
    }
}
