<?php

namespace Tests\Unit;

use App\Models\MatchRallyEvent;
use App\Services\MatchAdvancedStatsService;
use App\Services\MatchLineupService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/** Подача в сете по side-out + проверка «якорями» (ace / opp_serve_error). Без БД. */
class MatchServeAnalysisTest extends TestCase
{
    private const H = 10;
    private const A = 20;

    private function ev(int $winner, string $action): MatchRallyEvent
    {
        return new MatchRallyEvent(['team_id' => $winner, 'action_type' => $action]);
    }

    private function analyse(array $events, ?int $start = null, ?int $mark = null): array
    {
        $svc = new MatchAdvancedStatsService($this->createMock(MatchLineupService::class));

        return $svc->analyseServe(new Collection($events), self::H, self::A, $start, $mark);
    }

    public function test_consistent_set_without_mark_uses_first_rally_anchor(): void
    {
        // №1: подавал H и выиграл эйсом → №2 подаёт H; №2: выиграл A (ошибка подачи H) → №3 подаёт A
        $r = $this->analyse([
            $this->ev(self::H, MatchRallyEvent::ACTION_ACE),
            $this->ev(self::A, MatchRallyEvent::ACTION_OPP_SERVE_ERROR),
            $this->ev(self::A, MatchRallyEvent::ACTION_KILL),
        ]);

        $this->assertTrue($r['reliable']);
        $this->assertSame('anchor', $r['first_source']);
        $this->assertSame([1 => 'home', 2 => 'home', 3 => 'away'], $r['servers']);
    }

    public function test_rally_one_unknown_without_mark_or_anchor_but_rest_by_side_out(): void
    {
        $r = $this->analyse([
            $this->ev(self::H, MatchRallyEvent::ACTION_KILL),      // №1: подающий неизвестен
            $this->ev(self::A, MatchRallyEvent::ACTION_OPP_SERVE_ERROR), // №2: подаёт H (выиграл №1) — якорь согласуется
        ]);

        $this->assertTrue($r['reliable']);
        $this->assertArrayNotHasKey(1, $r['servers']);
        $this->assertSame('home', $r['servers'][2]);
    }

    public function test_contradicting_anchor_makes_set_unreliable(): void
    {
        $r = $this->analyse([
            $this->ev(self::H, MatchRallyEvent::ACTION_KILL),
            $this->ev(self::A, MatchRallyEvent::ACTION_ACE), // подавать должен H (выиграл №1), а эйс у A
        ]);

        $this->assertFalse($r['reliable']);
        $this->assertSame('contradiction', $r['reason']);
        $this->assertSame([2], $r['bad_rallies']);
        $this->assertSame([], $r['servers']);
    }

    public function test_no_anchor_no_mark_is_unverified(): void
    {
        $r = $this->analyse([$this->ev(self::H, MatchRallyEvent::ACTION_KILL), $this->ev(self::A, MatchRallyEvent::ACTION_KILL)]);

        $this->assertFalse($r['reliable']);
        $this->assertSame('no_anchor', $r['reason']);
    }

    public function test_mark_gives_first_server_and_conflicting_start_and_mark_is_unreliable(): void
    {
        $ok = $this->analyse([$this->ev(self::A, MatchRallyEvent::ACTION_KILL)], null, self::H);
        $this->assertTrue($ok['reliable']);
        $this->assertSame([1 => 'home'], $ok['servers']);

        $bad = $this->analyse([$this->ev(self::A, MatchRallyEvent::ACTION_KILL)], self::A, self::H);
        $this->assertFalse($bad['reliable']);
        $this->assertSame('conflict', $bad['reason']);
    }

    public function test_mark_contradicting_first_rally_anchor(): void
    {
        // по отметке подаёт H, но в розыгрыше №1 эйс у A
        $r = $this->analyse([$this->ev(self::A, MatchRallyEvent::ACTION_ACE)], null, self::H);

        $this->assertFalse($r['reliable']);
        $this->assertSame([1], $r['bad_rallies']);
    }
}
