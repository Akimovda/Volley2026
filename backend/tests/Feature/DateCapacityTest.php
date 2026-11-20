<?php

namespace Tests\Feature;

use App\Jobs\NotifyOrganizerRegistrationJob;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\EventIndexService;
use App\Services\EventRegistrationGuard;
use App\Services\OccurrenceAnnouncementMessageBuilder;
use App\Services\WaitlistService;
use Tests\Support\PgsqlTestCase;

/**
 * Авто увеличение кворума, ЭТАП 2 — ёмкость даты (event_occurrences.quorum_teams_count).
 * Дату «расширяем» вручную (на этапе 2 поле никто не заполняет; этап 3 — AutoQuorumService).
 * Запуск: tools/test-pgsql.sh --filter DateCapacityTest
 */
class DateCapacityTest extends PgsqlTestCase
{
    private function expand(EventOccurrence $occ, int $teams): EventOccurrence
    {
        $occ->forceFill(['quorum_teams_count' => $teams])->save();

        return $occ->fresh(['event.gameSettings']);
    }

    /** Заполнить основу даты по ЛИМИТАМ мероприятия (базовые 2 команды). */
    private function fillBase(EventOccurrence $occ): array
    {
        return $this->fillMain($occ);
    }

    public function test_expanded_date_in_series_has_own_capacity(): void
    {
        $a = $this->classic42();
        $b = $this->addOccurrence($a, 7);
        $a = $this->expand($a, 3);

        $this->assertSame(18, $a->effectiveMaxPlayers());
        $this->assertSame(12, $b->effectiveMaxPlayers());

        $this->fillBase($a);
        $this->fillBase($b);

        // 13-й на расширенную дату проходит (outside 8 → 12), на соседнюю — нет
        $this->joinHttp($this->makeUser(), $a, 'outside')->assertOk();
        $this->joinHttp($this->makeUser(), $a, 'setter')->assertOk();
        $this->joinHttp($this->makeUser(), $b, 'outside')->assertStatus(422);
        $this->assertSame(14, $this->activeRegs($a)->count());
        $this->assertSame(12, $this->activeRegs($b)->count());
        // слоты мероприятия не менялись
        $this->assertSame(['outside' => 8, 'setter' => 4], $this->slots($a));
    }

    public function test_expanded_classic_role_limits_are_exact(): void
    {
        $a = $this->expand($this->classic42(), 3);
        $this->fillBase($a);
        for ($i = 0; $i < 2; $i++) {
            $this->joinHttp($this->makeUser(), $a, 'setter')->assertOk(); // 4 → 6
        }
        $this->joinHttp($this->makeUser(), $a, 'setter')->assertStatus(422);
        for ($i = 0; $i < 4; $i++) {
            $this->joinHttp($this->makeUser(), $a, 'outside')->assertOk(); // 8 → 12
        }
        $this->joinHttp($this->makeUser(), $a, 'outside')->assertStatus(422);
        $this->assertSame(18, $this->activeRegs($a)->count());
    }

    public function test_guard_free_positions_by_date(): void
    {
        $a = $this->expand($this->classic42(), 3);
        $this->fillBase($a);

        $res  = app(EventRegistrationGuard::class)->check($this->makeUser(), $a, []);
        $free = collect($res->data['free_positions'] ?? [])->keyBy('key');
        $this->assertSame(2, $free['setter']['free']);
        $this->assertSame(6, $free['setter']['limit']);
        $this->assertSame(4, $free['outside']['free']);
        $this->assertSame(18, $res->meta['max_players'] ?? $res->data['meta']['max_players'] ?? null);
    }

    public function test_classic_without_positions_expanded(): void
    {
        $a = $this->expand($this->classicNoPositions(), 3);
        $this->fillBase($a); // 12
        for ($i = 0; $i < 6; $i++) {
            $this->joinHttp($this->makeUser(), $a, 'player')->assertOk();
        }
        $this->joinHttp($this->makeUser(), $a, 'player')->assertStatus(422);
        $this->assertSame(18, $this->activeRegs($a)->count());
    }

    public function test_beach_plain_expanded_to_three_pairs(): void
    {
        $a = $this->beach2x2();
        $b = $this->addOccurrence($a, 7);
        $a = $this->expand($a, 3);
        $this->fillBase($a);
        $this->fillBase($b);

        $this->joinHttp($this->makeUser(), $a, 'player')->assertOk();
        $this->joinHttp($this->makeUser(), $a, null)->assertOk(); // быстрая кнопка карточки — без позиции
        $this->joinHttp($this->makeUser(), $a, 'player')->assertStatus(422);
        $this->joinHttp($this->makeUser(), $b, 'player')->assertStatus(422);
        $this->assertSame(6, $this->activeRegs($a)->count());
        $this->assertSame(4, $this->activeRegs($b)->count());
    }

    public function test_beach_by_roles_expanded_blocker_and_defender(): void
    {
        $a = $this->expand($this->beach2x2Roles(), 3);
        $this->fillBase($a); // 2 + 2
        $this->joinHttp($this->makeUser(), $a, 'blocker')->assertOk();
        $this->joinHttp($this->makeUser(), $a, 'blocker')->assertStatus(422);
        $this->joinHttp($this->makeUser(), $a, 'defender')->assertOk();
        $this->joinHttp($this->makeUser(), $a, 'defender')->assertStatus(422);
        $this->assertSame(6, $this->activeRegs($a)->count());
    }

    public function test_waitlist_autobook_uses_date_capacity(): void
    {
        $a = $this->classic42();
        $this->fillBase($a);
        $wl = app(WaitlistService::class);
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();
        $u3 = $this->makeUser();
        $wl->join($a, $u1, ['outside']);
        $wl->join($a, $u2, ['setter']);
        $wl->join($a, $u3, ['middle']); // такой роли нет — останется в листе
        $this->assertFalse($this->isActive($a, $u1));

        // «расширение» (этап 3 сделает это сам): новые места → autoBookNext по ролям новых слотов
        $a = $this->expand($a, 3);
        $this->assertTrue($wl->autoBookNext($a, 'outside'));
        $this->assertTrue($wl->autoBookNext($a, 'setter'));
        $this->assertFalse($wl->autoBookNext($a, 'setter'), 'в очереди на setter больше никого');

        $this->assertTrue($this->isActive($a, $u1));
        $this->assertTrue($this->isActive($a, $u2));
        $this->assertTrue($this->inWaitlist($a, $u3));
        $this->assertTrue((bool) EventRegistration::where('occurrence_id', $a->id)->where('user_id', $u1->id)->value('auto_booked'));
    }

    public function test_waitlist_join_on_expanded_date_with_free_slot_books_immediately(): void
    {
        $a = $this->expand($this->classic42(), 3);
        $this->fillBase($a); // outside 8 из 12
        $u = $this->makeUser();
        app(WaitlistService::class)->join($a, $u, ['outside']);
        $this->assertTrue($this->isActive($a, $u));
    }

    public function test_cancel_on_expanded_full_date_books_next_from_waitlist(): void
    {
        $a = $this->expand($this->classic42(), 3);
        $regs = $this->fillBase($a);
        for ($i = 0; $i < 2; $i++) {
            $regs[] = $this->seatDirect($a, 'setter');
        }
        for ($i = 0; $i < 4; $i++) {
            $regs[] = $this->seatDirect($a, 'outside');
        }
        $this->assertSame(18, $this->activeRegs($a)->count());
        $u = $this->makeUser();
        app(WaitlistService::class)->join($a, $u, ['setter']);
        $this->assertFalse($this->isActive($a, $u), 'дата полна по ёмкости даты');

        $this->cancelReg(collect($regs)->firstWhere('position', 'setter'));
        $this->assertTrue($this->isActive($a, $u));
        $this->assertSame(18, $this->activeRegs($a)->count());
    }

    public function test_beach_waitlist_autobook_uses_date_capacity(): void
    {
        $a = $this->beach2x2();
        $this->fillBase($a);
        $u = $this->makeUser();
        app(WaitlistService::class)->join($a, $u, []);
        $this->assertFalse($this->isActive($a, $u));

        $a = $this->expand($a, 3);
        $this->assertTrue(app(WaitlistService::class)->autoBookNext($a, 'player'));
        $this->assertTrue($this->isActive($a, $u));
    }

    public function test_announcement_shows_date_capacity(): void
    {
        $a = $this->classic42(['reserve' => 2]);
        $b = $this->addOccurrence($a, 7);
        $a = $this->expand($a, 3);
        $this->seatDirect($a, 'setter');
        $this->seatDirect($b, 'setter');

        $builder = app(OccurrenceAnnouncementMessageBuilder::class);
        $this->assertStringContainsString('Осталось мест: 19 из 20', $builder->build($a->fresh('event'))->text);
        $this->assertStringContainsString('Осталось мест: 13 из 14', $builder->build($b->fresh('event'))->text);
    }

    public function test_events_feed_availability_by_date(): void
    {
        $a = $this->classic42();
        $b = $this->addOccurrence($a, 7);
        $a = $this->expand($a, 3);
        $this->seatDirect($a, 'setter');

        $occs = EventOccurrence::with('event.gameSettings')->whereIn('id', [$a->id, $b->id])->orderBy('starts_at')->get();
        app(EventIndexService::class)->cardData(null, $occs);

        $this->assertSame(['registered' => 1, 'remaining' => 17, 'max' => 18], $occs[0]->availability);
        $this->assertSame(['registered' => 0, 'remaining' => 12, 'max' => 12], $occs[1]->availability);
    }

    public function test_organizer_notification_counts_by_date(): void
    {
        $a = $this->expand($this->classic42(['reserve' => 2]), 3);
        $player = $this->seatDirect($a, 'setter')->user;

        $job = new NotifyOrganizerRegistrationJob($a->id, $player->id, 'registered');
        $m = new \ReflectionMethod($job, 'buildExtra');
        $m->setAccessible(true);
        $extra = $m->invoke($job, $a->fresh(['event.gameSettings']), $player);

        $this->assertSame('1', $extra['booked_count']);
        $this->assertSame('19', $extra['available_count'], '18 основа + 2 запасных − 1');
    }

    public function test_organizer_manual_add_respects_date_capacity(): void
    {
        $a = $this->expand($this->classic42(), 3);
        $this->fillBase($a);
        $u = $this->makeUser();

        $this->actingAs($this->organizer())
            ->post("/events/{$a->event_id}/registrations/add", ['user_id' => $u->id, 'position' => 'outside', 'occurrence_id' => $a->id])
            ->assertRedirect();
        $this->assertTrue($this->isActive($a, $u), 'outside 8 из 12 на расширенной дате — есть место');

        $b = $this->classic42();
        $this->fillBase($b);
        $v = $this->makeUser();
        $this->actingAs($this->organizer())
            ->post("/events/{$b->event_id}/registrations/add", ['user_id' => $v->id, 'position' => 'outside', 'occurrence_id' => $b->id])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertFalse($this->isActive($b, $v));
    }

    /** Регрессия (2026-11-20): страница «Управление записями» с выбранной датой не падает; ёмкость — по дате. */
    public function test_registrations_management_page_shows_date_capacity(): void
    {
        $a = $this->classic42();
        $b = $this->addOccurrence($a, 7);
        $a = $this->expand($a, 3);

        $this->actingAs($this->organizer())->get("/events/{$a->event_id}/registrations?occurrence={$a->id}")
            ->assertOk()->assertSee('18/18');
        $this->actingAs($this->organizer())->get("/events/{$b->event_id}/registrations?occurrence={$b->id}")
            ->assertOk()->assertSee('12/12');
    }

    public function test_not_expanded_date_unchanged(): void
    {
        $a = $this->classic42();
        $this->assertNull($a->quorum_teams_count);
        $this->assertSame(12, $a->effectiveMaxPlayers());
        $this->fillBase($a);
        $this->joinHttp($this->makeUser(), $a, 'outside')->assertStatus(422);
    }
}
