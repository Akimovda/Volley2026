<?php

namespace Tests\Unit;

use App\Services\AutoQuorumService;
use Tests\TestCase;

/** Расчёт авто увеличения кворума без БД (docs/auto-quorum.md). */
class AutoQuorumCalcTest extends TestCase
{
    public function test_thresholds_example_from_spec(): void
    {
        // perTeam=5, base=2, max=4 → «3-я команда откроется при 15 записавшихся, 4-я — при 20»
        $this->assertSame([3 => 15, 4 => 20], AutoQuorumService::thresholds(2, 4, 5));
        $this->assertSame([], AutoQuorumService::thresholds(3, 3, 5));
    }

    public function test_target_teams(): void
    {
        $this->assertSame(2, AutoQuorumService::targetTeams(2, 14, 5, 4));
        $this->assertSame(3, AutoQuorumService::targetTeams(2, 15, 5, 4));
        $this->assertSame(3, AutoQuorumService::targetTeams(2, 19, 5, 4));
        $this->assertSame(4, AutoQuorumService::targetTeams(2, 20, 5, 4));
        $this->assertSame(4, AutoQuorumService::targetTeams(2, 40, 5, 4), 'не больше максимума');
        $this->assertSame(3, AutoQuorumService::targetTeams(3, 5, 5, 4), 'только рост');
        $this->assertSame(5, AutoQuorumService::targetTeams(5, 40, 5, 4), 'дата уже больше максимума — не уменьшаем');
        $this->assertSame(2, AutoQuorumService::targetTeams(2, 99, 0, 4), 'perTeam 0 — ничего');
        $this->assertSame(3, AutoQuorumService::targetTeams(2, 6, 2, 4), 'пляж: 6 записавшихся → 3 пары');
    }

    public function test_default_players_per_team(): void
    {
        $this->assertSame(5, AutoQuorumService::defaultPlayersPerTeam('classic', '4x2', null));
        $this->assertSame(5, AutoQuorumService::defaultPlayersPerTeam('classic', '5x1', 'without_libero'));
        $this->assertSame(6, AutoQuorumService::defaultPlayersPerTeam('classic', '5x1', 'with_libero'));
        $this->assertSame(3, AutoQuorumService::defaultPlayersPerTeam('classic', '4x4', null));
        $this->assertSame(2, AutoQuorumService::defaultPlayersPerTeam('beach', '2x2', null));
        $this->assertSame(2, AutoQuorumService::defaultPlayersPerTeam('beach', null, null));
    }

    public function test_quorum_lock_key_never_collides_with_position_keys(): void
    {
        $this->assertLessThan(0, AutoQuorumService::QUORUM_KEY);
        foreach (['setter', 'outside', 'opposite', 'middle', 'libero', 'reserve', 'player', 'blocker', 'defender'] as $p) {
            $this->assertGreaterThanOrEqual(0, crc32($p) & 0x7fffffff);
        }
    }
}
