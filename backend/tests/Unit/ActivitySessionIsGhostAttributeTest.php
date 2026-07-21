<?php

namespace Tests\Unit;

use App\Models\ActivitySession;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Расширение getIsGhostAttribute() (report_activity_ghost_duplicates_2026-07-21.md):
 * раньше ловил только status=completed-заглушки, теперь и зависшие status=live
 * старше activity.sync_stale_hours. Чистая логика на несохранённой модели, без БД.
 */
class ActivitySessionIsGhostAttributeTest extends TestCase
{
    private function makeSession(array $attrs): ActivitySession
    {
        return new ActivitySession(array_merge([
            'status'        => 'completed',
            'started_at'    => now(),
            'duration_sec'  => 0,
            'samples_count' => 0,
            'jump_count'    => 0,
        ], $attrs));
    }

    public function test_completed_short_and_empty_is_ghost(): void
    {
        $session = $this->makeSession(['status' => 'completed', 'duration_sec' => 5]);

        $this->assertTrue($session->is_ghost);
    }

    public function test_completed_long_enough_is_not_ghost(): void
    {
        $session = $this->makeSession(['status' => 'completed', 'duration_sec' => 60]);

        $this->assertFalse($session->is_ghost);
    }

    public function test_completed_with_samples_is_not_ghost_even_if_short(): void
    {
        $session = $this->makeSession(['status' => 'completed', 'duration_sec' => 5, 'samples_count' => 10]);

        $this->assertFalse($session->is_ghost);
    }

    public function test_live_stale_and_empty_is_ghost(): void
    {
        $session = $this->makeSession([
            'status'     => 'live',
            'started_at' => Carbon::now()->subHours(7),
        ]);

        $this->assertTrue($session->is_ghost);
    }

    public function test_live_fresh_is_not_ghost(): void
    {
        $session = $this->makeSession([
            'status'     => 'live',
            'started_at' => Carbon::now()->subHours(1),
        ]);

        $this->assertFalse($session->is_ghost);
    }

    public function test_live_stale_with_jumps_is_not_ghost(): void
    {
        $session = $this->makeSession([
            'status'     => 'live',
            'started_at' => Carbon::now()->subHours(7),
            'jump_count' => 3,
        ]);

        $this->assertFalse($session->is_ghost);
    }
}
