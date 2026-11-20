<?php

namespace Tests\Unit;

use App\Services\GameCalculator;
use Tests\TestCase;

/**
 * Роли и размер команды по схемам (config/volleyball.php). Без БД.
 * Нужен для авто увеличения кворума (docs/auto-quorum.md): +1 команда = roles × 1.
 */
class GameCalculatorTest extends TestCase
{
    public function test_classic_4x2(): void
    {
        $c = GameCalculator::calculate('4x2', null, 2);
        $this->assertSame(6, $c['team_size']);
        $this->assertSame(12, $c['max_players']);
        $this->assertSame(['setter' => 2, 'outside' => 4], $c['roles']);
    }

    public function test_classic_4x4(): void
    {
        $c = GameCalculator::calculate('4x4', null, 2);
        $this->assertSame(4, $c['team_size']);
        $this->assertSame(['setter' => 1, 'outside' => 2, 'opposite' => 1], $c['roles']);
    }

    public function test_classic_5x1_without_libero(): void
    {
        $c = GameCalculator::calculate('5x1', 'without_libero', 2);
        $this->assertSame(6, $c['team_size']);
        $this->assertSame(12, $c['max_players']);
        $this->assertSame(['setter' => 1, 'outside' => 2, 'opposite' => 1, 'middle' => 2], $c['roles']);
    }

    public function test_classic_5x1_with_libero(): void
    {
        $c = GameCalculator::calculate('5x1', 'with_libero', 3);
        $this->assertSame(7, $c['team_size']);
        $this->assertSame(21, $c['max_players']);
        $this->assertSame(1, $c['roles']['libero']);
    }

    public function test_5x1_null_libero_mode_is_without_libero(): void
    {
        $c = GameCalculator::calculate('5x1', null, 2);
        $this->assertSame(6, $c['team_size']);
        $this->assertArrayNotHasKey('libero', $c['roles']);
    }

    public function test_beach_schemes_single_player_role(): void
    {
        foreach (['2x2' => 2, '3x3' => 3] as $subtype => $size) {
            $c = GameCalculator::calculate($subtype, null, 2);
            $this->assertSame($size, $c['team_size'], $subtype);
            $this->assertSame(['player' => $size], $c['roles'], $subtype);
        }
        // пляжная 4×4 хранится под ключом 4x4_beach (ключ 4x4 — классика)
        $c = GameCalculator::calculate('4x4_beach', null, 2);
        $this->assertSame(['player' => 4], $c['roles']);
    }

    public function test_beach_2x2_by_roles(): void
    {
        $c = GameCalculator::calculate('2x2', null, 3, true);
        $this->assertSame(['blocker' => 1, 'defender' => 1], $c['roles']);
        $this->assertSame(6, $c['max_players']);
        $this->assertSame(['blocker' => 1, 'defender' => 1], GameCalculator::beachRoles('2x2'));
        $this->assertNull(GameCalculator::beachRoles('3x3'));
    }

    public function test_beach_roles_flag_ignored_for_other_schemes(): void
    {
        $c = GameCalculator::calculate('3x3', null, 2, true);
        $this->assertSame(['player' => 3], $c['roles']);
    }

    public function test_unknown_subtype(): void
    {
        $this->assertSame(['team_size' => 0, 'max_players' => 0, 'roles' => []], GameCalculator::calculate('9x9', null, 2));
    }

    public function test_roles_sum_equals_team_size_for_all_schemes(): void
    {
        foreach (GameCalculator::config() as $key => $scheme) {
            $this->assertSame($scheme['team_size'], array_sum($scheme['roles']), $key);
        }
    }
}
