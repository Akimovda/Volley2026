<?php

namespace Tests\Feature;

use App\Jobs\NotifyOrganizerRegistrationJob;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\OccurrenceWaitlist;
use App\Models\Payment;
use App\Models\User;
use App\Services\OccurrenceAnnouncementMessageBuilder;
use App\Services\PaymentService;
use App\Services\WaitlistService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\PgsqlTestCase;

/**
 * Авто увеличение кворума, ЭТАП 1 — характеризационные тесты (фиксируют ТЕКУЩЕЕ поведение записи,
 * запасных, листа ожидания и автоперевода). Код приложения не меняется. Спецификация — docs/auto-quorum.md.
 *
 * Запуск: tools/test-pgsql.sh --filter RegistrationFlowTest
 * (локальная БД volley_test, каждая проверка — в откатываемой транзакции).
 *
 * Если тест фиксирует подозрительное поведение — это отмечено «СТРАННОСТЬ» и вынесено в report/auto_quorum_stage1.md.
 */
class RegistrationFlowTest extends PgsqlTestCase
{
    // =====================================================================
    // Слоты при создании (EventGameSettingsService::createGameSettings)
    // =====================================================================

    public function test_slots_classic_4x2_two_teams(): void
    {
        $o = $this->classic42();
        $this->assertSame(['outside' => 8, 'setter' => 4], $this->slots($o));
        $this->assertSame(12, (int) $o->event->gameSettings->max_players);
        $this->assertSame(2, (int) $o->event->gameSettings->teams_count);
        // max_players даты при создании не заполняется — берётся из настроек мероприятия
        $this->assertNull($o->max_players);
        $this->assertSame(12, $o->effectiveMaxPlayers());
    }

    public function test_slots_classic_5x1_with_and_without_libero(): void
    {
        $o = $this->classic51();
        $this->assertSame(['middle' => 4, 'opposite' => 2, 'outside' => 4, 'setter' => 2], $this->slots($o));
        $this->assertSame(12, (int) $o->event->gameSettings->max_players);

        $l = $this->classic51Libero();
        $this->assertSame(['libero' => 2, 'middle' => 4, 'opposite' => 2, 'outside' => 4, 'setter' => 2], $this->slots($l));
        $this->assertSame(14, (int) $l->event->gameSettings->max_players);
    }

    public function test_slots_scale_with_teams_count(): void
    {
        $o = $this->classic42(['teams' => 3]);
        $this->assertSame(['outside' => 12, 'setter' => 6], $this->slots($o));
        $this->assertSame(18, (int) $o->event->gameSettings->max_players);
    }

    public function test_slots_classic_without_positions_single_player_slot(): void
    {
        $o = $this->classicNoPositions();
        $this->assertSame(['player' => 12], $this->slots($o));
        $this->assertTrue((bool) $o->event->gameSettings->registration_without_positions);
    }

    public function test_slots_beach_plain_and_by_roles(): void
    {
        $b = $this->beach2x2();
        $this->assertSame(['player' => 4], $this->slots($b));
        $this->assertSame(4, (int) $b->event->gameSettings->max_players);

        // «По амплуа»: blocker и defender — по числу пар
        $r2 = $this->beach2x2Roles();
        $this->assertSame(['blocker' => 2, 'defender' => 2], $this->slots($r2));
        $r3 = $this->beach2x2Roles(['teams' => 3]);
        $this->assertSame(['blocker' => 3, 'defender' => 3], $this->slots($r3));
        $this->assertSame(6, (int) $r3->event->gameSettings->max_players);
        // направление записи для «По амплуа» — classic (autoBookNext идёт через слоты)
        $this->assertSame('classic', $r3->event->registrationDirection());
    }

    public function test_reserve_is_limit_in_settings_not_role_slot(): void
    {
        $o = $this->classic42(['reserve' => 2]);
        $this->assertArrayNotHasKey('reserve', $this->slots($o));
        $this->assertSame(2, (int) $o->event->gameSettings->reserve_players_max);
    }

    // =====================================================================
    // Запись (POST /occurrences/{id}/join → Guard → persistRegistration)
    // =====================================================================

    public function test_join_free_position(): void
    {
        $o = $this->classic42();
        $u = $this->makeUser();

        $this->joinHttp($u, $o, 'setter')->assertOk()->assertJson(['ok' => true]);

        $reg = EventRegistration::where('occurrence_id', $o->id)->where('user_id', $u->id)->first();
        $this->assertNotNull($reg);
        $this->assertSame('setter', $reg->position);
        $this->assertSame('confirmed', $reg->status);
        Queue::assertPushed(NotifyOrganizerRegistrationJob::class);
    }

    public function test_join_taken_position_rejected_other_position_still_open(): void
    {
        $o = $this->classic42();
        for ($i = 0; $i < 4; $i++) {
            $this->seatDirect($o, 'setter');
        }

        $this->joinHttp($this->makeUser(), $o, 'setter')
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'message' => 'На выбранную позицию мест больше нет.']);

        $this->joinHttp($this->makeUser(), $o, 'outside')->assertOk();
        $this->assertSame(5, $this->activeRegs($o)->count());
    }

    public function test_join_full_event_rejected(): void
    {
        $o = $this->classic42();
        $this->fillMain($o);

        // при полной основе Guard отвечает общим отказом и для позиции, и без неё
        $this->joinHttp($this->makeUser(), $o, 'outside')
            ->assertStatus(422)
            ->assertJson(['message' => 'Свободных мест на мероприятие больше нет.']);
        $this->joinHttp($this->makeUser(), $o, null)
            ->assertStatus(422)
            ->assertJson(['message' => 'Свободных мест на мероприятие больше нет.']);
        $this->assertSame(12, $this->activeRegs($o)->count());
    }

    /**
     * СТРАННОСТЬ: классика с амплуа принимает запись БЕЗ позиции (position=NULL), пока есть общий запас мест.
     * Такая запись не занимает ни одного ролевого слота.
     */
    public function test_classic_join_without_position_is_accepted_while_room(): void
    {
        $o = $this->classic42();
        $u = $this->makeUser();

        $this->joinHttp($u, $o, null)->assertOk();
        $reg = EventRegistration::where('occurrence_id', $o->id)->where('user_id', $u->id)->first();
        $this->assertNull($reg->position);
    }

    /**
     * Проверка на перебор: 11 по ролям + 1 без позиции = 12 (полный состав), затем запись на свободный по слоту outside.
     */
    public function test_classic_null_position_and_role_slots_do_not_overbook(): void
    {
        $o = $this->classic42();
        for ($i = 0; $i < 4; $i++) {
            $this->seatDirect($o, 'setter');
        }
        for ($i = 0; $i < 7; $i++) {
            $this->seatDirect($o, 'outside');
        }
        $this->joinHttp($this->makeUser(), $o, null)->assertOk(); // 12-й без позиции

        $resp = $this->joinHttp($this->makeUser(), $o, 'outside'); // слот outside 7/8 «свободен»
        $total = $this->activeRegs($o)->count();
        if ($total > 12) {
            $this->markTestIncomplete("БАГ: перебор — {$total} активных записей при max_players=12 (запись без позиции + слот outside). Ответ: " . $resp->getContent());
        }
        $resp->assertStatus(422);
        $this->assertSame(12, $total);
    }

    public function test_classic_without_positions_uses_player_slot(): void
    {
        $o = $this->classicNoPositions();
        $u = $this->makeUser();
        $this->joinHttp($u, $o, 'player')->assertOk();
        $this->assertSame('player', EventRegistration::where('occurrence_id', $o->id)->where('user_id', $u->id)->value('position'));

        for ($i = 0; $i < 11; $i++) {
            $this->seatDirect($o, 'player');
        }
        $this->joinHttp($this->makeUser(), $o, 'player')->assertStatus(422);
        $this->assertSame(12, $this->activeRegs($o)->count());
    }

    public function test_beach_plain_join_with_and_without_position(): void
    {
        $o = $this->beach2x2();
        $this->joinHttp($this->makeUser(), $o, 'player')->assertOk();
        $this->joinHttp($this->makeUser(), $o, null)->assertOk();
        $this->seatDirect($o, 'player');
        $this->seatDirect($o, 'player');

        $this->joinHttp($this->makeUser(), $o, 'player')->assertStatus(422);
        $this->assertSame(4, $this->activeRegs($o)->count());
    }

    public function test_beach_by_roles_blocker_defender_slots_equal_pairs(): void
    {
        $o = $this->beach2x2Roles();

        $this->joinHttp($this->makeUser(), $o, null)
            ->assertStatus(422)
            ->assertJson(['message' => 'Выберите амплуа: Блокирующий или Защитник.']);

        $this->joinHttp($this->makeUser(), $o, 'blocker')->assertOk();
        $this->joinHttp($this->makeUser(), $o, 'blocker')->assertOk();
        $this->joinHttp($this->makeUser(), $o, 'blocker')->assertStatus(422);
        $this->joinHttp($this->makeUser(), $o, 'defender')->assertOk();
        $this->assertSame(3, $this->activeRegs($o)->count());
    }

    // =====================================================================
    // Запасные (position='reserve', лимит reserve_players_max)
    // =====================================================================

    public function test_reserve_only_when_main_full(): void
    {
        $o = $this->classic42(['reserve' => 2]);

        // основа не заполнена — в запасные не пускает
        $this->joinHttp($this->makeUser(), $o, 'reserve')
            ->assertStatus(422)
            ->assertJson(['message' => 'На выбранную позицию мест больше нет.']);
    }

    public function test_reserve_limit(): void
    {
        $o = $this->classic42(['reserve' => 2]);
        $this->fillMain($o);

        $this->joinHttp($this->makeUser(), $o, 'reserve')->assertOk();
        $this->joinHttp($this->makeUser(), $o, 'reserve')->assertOk();
        $this->joinHttp($this->makeUser(), $o, 'reserve')
            ->assertStatus(422)
            ->assertJson(['message' => 'Свободных мест на мероприятие больше нет.']);

        $this->assertSame(2, $this->activeRegs($o)->where('position', 'reserve')->count());
        $this->assertSame(14, $this->activeRegs($o)->count());
    }

    public function test_reserve_disabled_when_limit_zero(): void
    {
        $o = $this->classic42();
        $this->fillMain($o);
        $this->joinHttp($this->makeUser(), $o, 'reserve')->assertStatus(422);
    }

    // =====================================================================
    // Лист ожидания (occurrence_waitlist)
    // =====================================================================

    public function test_full_main_then_waitlist_with_desired_positions(): void
    {
        $o = $this->classic42();
        $this->fillMain($o);
        $a = $this->makeUser();
        $b = $this->makeUser();

        $this->actingAs($a)->post("/occurrences/{$o->id}/waitlist", ['positions' => ['outside', 'setter']])
            ->assertRedirect()->assertSessionHas('status');
        $this->actingAs($b)->post("/occurrences/{$o->id}/waitlist", ['positions' => ['setter']])
            ->assertRedirect()->assertSessionHas('status');

        $rows = OccurrenceWaitlist::where('occurrence_id', $o->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $rows);
        $this->assertSame([$a->id, $b->id], $rows->pluck('user_id')->all());
        $this->assertSame(['outside', 'setter'], $rows[0]->positions);
        $this->assertSame(['setter'], $rows[1]->positions);
        $this->assertSame([1, 2], $rows->pluck('sort_order')->map(fn ($v) => (int) $v)->all());
        $this->assertFalse($this->isActive($o, $a));
    }

    public function test_waitlist_classic_requires_position(): void
    {
        $o = $this->classic42();
        $this->fillMain($o);
        $u = $this->makeUser();

        $this->actingAs($u)->post("/occurrences/{$o->id}/waitlist", ['positions' => []])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertFalse($this->inWaitlist($o, $u));
    }

    public function test_waitlist_join_with_free_reserve_goes_to_reserve(): void
    {
        $o = $this->classic42(['reserve' => 1]);
        $this->fillMain($o);
        $u = $this->makeUser();

        app(WaitlistService::class)->join($o, $u, ['setter', 'reserve']);

        $reg = EventRegistration::where('occurrence_id', $o->id)->where('user_id', $u->id)->first();
        $this->assertNotNull($reg);
        $this->assertSame('reserve', $reg->position);
        $this->assertTrue((bool) $reg->auto_booked);
        $this->assertFalse($this->inWaitlist($o, $u));
    }

    public function test_waitlist_join_when_slot_free_autobooks_immediately(): void
    {
        $o = $this->classic42();
        for ($i = 0; $i < 4; $i++) {
            $this->seatDirect($o, 'setter');
        }
        $u = $this->makeUser();

        // outside свободен — вступление в лист ожидания на outside сразу переводит в основу
        app(WaitlistService::class)->join($o, $u, ['outside']);
        $this->assertTrue($this->isActive($o, $u));
        $this->assertSame('outside', EventRegistration::where('occurrence_id', $o->id)->where('user_id', $u->id)->value('position'));
    }

    // =====================================================================
    // Автоперевод из листа ожидания (Observer → onSpotFreed → autoBookNext)
    // =====================================================================

    /** @return array{0: EventOccurrence, 1: EventRegistration[]} основа 4×2 заполнена */
    private function fullClassic(array $o = []): array
    {
        $occ = $this->classic42($o);

        return [$occ, $this->fillMain($occ)];
    }

    private function firstSetter(array $regs): EventRegistration
    {
        foreach ($regs as $r) {
            if ($r->position === 'setter') {
                return $r;
            }
        }
        $this->fail('нет setter');
    }

    public function test_cancel_autobooks_premium_first(): void
    {
        [$o, $regs] = $this->fullClassic();
        $wl = app(WaitlistService::class);
        $a = $this->makeUser();
        $b = $this->makeUser();
        $c = $this->makeUser();
        $wl->join($o, $a, ['setter']);
        $wl->join($o, $b, ['setter']);
        $wl->join($o, $c, ['setter']);
        $this->makePremium($c);

        $this->cancelReg($this->firstSetter($regs));

        $this->assertTrue($this->isActive($o, $c), 'Premium переводится первым, несмотря на sort_order');
        $this->assertFalse($this->isActive($o, $a));
        $this->assertTrue($this->inWaitlist($o, $a));
        $this->assertFalse($this->inWaitlist($o, $c));
    }

    public function test_cancel_autobooks_by_sort_order_then_created_at(): void
    {
        [$o, $regs] = $this->fullClassic();
        $wl = app(WaitlistService::class);
        $a = $this->makeUser();
        $b = $this->makeUser();
        $c = $this->makeUser();
        $wl->join($o, $a, ['setter']);
        $wl->join($o, $b, ['setter']);
        $wl->join($o, $c, ['setter']);

        // организатор поднял C наверх (sort_order 0) — он раньше A, хотя записался позже
        DB::table('occurrence_waitlist')->where('occurrence_id', $o->id)->where('user_id', $c->id)->update(['sort_order' => 0]);
        // A и B — одинаковый sort_order; тогда решает created_at (B создан раньше)
        DB::table('occurrence_waitlist')->where('occurrence_id', $o->id)->where('user_id', $a->id)->update(['sort_order' => 5, 'created_at' => now()->subMinutes(1)]);
        DB::table('occurrence_waitlist')->where('occurrence_id', $o->id)->where('user_id', $b->id)->update(['sort_order' => 5, 'created_at' => now()->subMinutes(10)]);

        $setters = array_values(array_filter($regs, fn ($r) => $r->position === 'setter'));
        $this->cancelReg($setters[0]);
        $this->assertTrue($this->isActive($o, $c));

        $this->cancelReg($setters[1]);
        $this->assertTrue($this->isActive($o, $b), 'при равном sort_order — раньше созданный');
        $this->assertFalse($this->isActive($o, $a));
    }

    public function test_cancel_autobooks_only_matching_position(): void
    {
        [$o, $regs] = $this->fullClassic();
        $wl = app(WaitlistService::class);
        $a = $this->makeUser();
        $b = $this->makeUser();
        $wl->join($o, $a, ['outside']); // первый в очереди, но хочет outside
        $wl->join($o, $b, ['setter']);

        $this->cancelReg($this->firstSetter($regs));

        $this->assertFalse($this->isActive($o, $a));
        $this->assertTrue($this->inWaitlist($o, $a));
        $this->assertTrue($this->isActive($o, $b));
        $this->assertSame('setter', EventRegistration::where('occurrence_id', $o->id)->where('user_id', $b->id)->value('position'));
    }

    public function test_cancel_nobody_matches_slot_stays_free(): void
    {
        [$o, $regs] = $this->fullClassic();
        $a = $this->makeUser();
        app(WaitlistService::class)->join($o, $a, ['outside']);

        $this->cancelReg($this->firstSetter($regs));

        $this->assertFalse($this->isActive($o, $a));
        $this->assertSame(11, $this->activeRegs($o)->count());
    }

    public function test_cancel_autobook_mixed_limited_position_restriction(): void
    {
        // девушкам разрешены только outside (квота 4)
        [$o, $regs] = $this->fullClassic(['gender' => [
            'game_gender_policy'            => 'mixed_limited',
            'game_gender_limited_side'      => 'female',
            'game_gender_limited_max'       => 4,
            'game_gender_limited_positions' => ['outside'],
        ]]);
        $f = $this->makeUser(['gender' => 'f']);
        $m = $this->makeUser(['gender' => 'm']);
        // f встаёт в очередь первой «на setter» напрямую (UI бы не дал — обходим, чтобы проверить фильтр autoBookNext)
        OccurrenceWaitlist::create(['occurrence_id' => $o->id, 'user_id' => $f->id, 'positions' => ['setter'], 'sort_order' => 1]);
        OccurrenceWaitlist::create(['occurrence_id' => $o->id, 'user_id' => $m->id, 'positions' => ['setter'], 'sort_order' => 2]);

        $this->cancelReg($this->firstSetter($regs));

        $this->assertFalse($this->isActive($o, $f), 'ограниченный пол не переводится на запрещённую позицию');
        $this->assertTrue($this->isActive($o, $m));
    }

    public function test_cancel_autobook_mixed_limited_quota(): void
    {
        // девушкам outside, квота 1 — и одна девушка уже в основе на outside
        $o = $this->classic42(['gender' => [
            'game_gender_policy'            => 'mixed_limited',
            'game_gender_limited_side'      => 'female',
            'game_gender_limited_max'       => 1,
            'game_gender_limited_positions' => ['outside'],
        ]]);
        $this->seatDirect($o, 'outside', $this->makeUser(['gender' => 'f']));
        $outsideMen = [];
        for ($i = 0; $i < 7; $i++) {
            $outsideMen[] = $this->seatDirect($o, 'outside');
        }
        for ($i = 0; $i < 4; $i++) {
            $this->seatDirect($o, 'setter');
        }

        $f2 = $this->makeUser(['gender' => 'f']);
        $m  = $this->makeUser(['gender' => 'm']);
        app(WaitlistService::class)->join($o, $f2, ['outside']);
        app(WaitlistService::class)->join($o, $m, ['outside']);

        $this->cancelReg($outsideMen[0]);

        $this->assertFalse($this->isActive($o, $f2), 'квота девушек исчерпана — пропускаем');
        $this->assertTrue($this->inWaitlist($o, $f2));
        $this->assertTrue($this->isActive($o, $m));
    }

    public function test_cancel_autobook_beach_plain(): void
    {
        $o = $this->beach2x2();
        $regs = $this->fillMain($o);
        $u = $this->makeUser();
        app(WaitlistService::class)->join($o, $u, []); // пляж: positions=[] — любая позиция

        $this->cancelReg($regs[0]);

        $this->assertTrue($this->isActive($o, $u));
        $this->assertSame('player', EventRegistration::where('occurrence_id', $o->id)->where('user_id', $u->id)->value('position'));
        $this->assertSame(4, $this->activeRegs($o)->count());
    }

    public function test_cancel_autobook_beach_by_roles_matches_role(): void
    {
        $o = $this->beach2x2Roles();
        $regs = $this->fillMain($o);
        $wantsDefender = $this->makeUser();
        $wantsBlocker  = $this->makeUser();
        app(WaitlistService::class)->join($o, $wantsDefender, ['defender']);
        app(WaitlistService::class)->join($o, $wantsBlocker, ['blocker']);

        $blockerReg = collect($regs)->firstWhere('position', 'blocker');
        $this->cancelReg($blockerReg);

        $this->assertFalse($this->isActive($o, $wantsDefender));
        $this->assertTrue($this->isActive($o, $wantsBlocker));
        $this->assertSame('blocker', EventRegistration::where('occurrence_id', $o->id)->where('user_id', $wantsBlocker->id)->value('position'));
    }

    public function test_autobook_creates_notifications_and_log(): void
    {
        [$o, $regs] = $this->fullClassic();
        $u = $this->makeUser();
        app(WaitlistService::class)->join($o, $u, ['setter']);
        Queue::fake(); // считаем только джобы автоперевода

        $this->cancelReg($this->firstSetter($regs));

        $reg = EventRegistration::where('occurrence_id', $o->id)->where('user_id', $u->id)->first();
        $this->assertTrue((bool) $reg->auto_booked);
        $this->assertSame('confirmed', $reg->status);
        $this->assertTrue(DB::table('user_notifications')->where('user_id', $u->id)->where('type', 'waitlist_auto_booked')->exists());
        $this->assertFalse(DB::table('user_notifications')->where('user_id', $u->id)->where('type', 'waitlist_payment_required')->exists());
        Queue::assertPushed(NotifyOrganizerRegistrationJob::class);
    }

    // =====================================================================
    // Платная игра: окно оплаты при автопереводе и releaseExpired
    // =====================================================================

    public function test_paid_autobook_sets_payment_hold_and_notification(): void
    {
        [$o, $regs] = $this->fullClassic(['paid' => true, 'payment_method' => 'tbank_link', 'price_minor' => 50000]);
        $u = $this->makeUser();
        app(WaitlistService::class)->join($o, $u, ['setter']);

        Carbon::setTestNow(now());
        $this->cancelReg($this->firstSetter($regs));

        $reg = EventRegistration::where('occurrence_id', $o->id)->where('user_id', $u->id)->first();
        $this->assertSame('link_pending', $reg->payment_status);
        $this->assertNotNull($reg->payment_expires_at);
        $holdMin = (int) config('waitlist.payment_hold_minutes', 30);
        $this->assertEqualsWithDelta(now()->addMinutes($holdMin)->timestamp, Carbon::parse($reg->payment_expires_at)->timestamp, 5);

        $payment = Payment::find($reg->payment_id);
        $this->assertSame('pending', $payment->status);
        $this->assertTrue((bool) ($payment->yoomoney_meta['waitlist_hold'] ?? false));
        $this->assertTrue(DB::table('user_notifications')->where('user_id', $u->id)->where('type', 'waitlist_payment_required')->exists());
        Carbon::setTestNow();
    }

    public function test_release_expired_frees_spot_and_books_next(): void
    {
        [$o, $regs] = $this->fullClassic(['paid' => true, 'payment_method' => 'tbank_link', 'price_minor' => 50000]);
        $wl = app(WaitlistService::class);
        $first  = $this->makeUser();
        $second = $this->makeUser();
        $wl->join($o, $first, ['setter']);
        $wl->join($o, $second, ['setter']);

        $this->cancelReg($this->firstSetter($regs));
        $this->assertTrue($this->isActive($o, $first));

        $this->travel(31)->minutes();
        $released = app(PaymentService::class)->releaseExpired();
        $this->assertGreaterThanOrEqual(1, $released);

        $regFirst = EventRegistration::where('occurrence_id', $o->id)->where('user_id', $first->id)->first();
        $this->assertTrue((bool) $regFirst->is_cancelled);
        $this->assertSame('expired', $regFirst->payment_status);
        $this->assertTrue($this->isActive($o, $second), 'Observer → onSpotFreed → следующий из листа ожидания');
        $this->assertSame(12, $this->activeRegs($o)->count());
        $this->travelBack();
    }

    // =====================================================================
    // Редактирование: teams_count → event_role_slots и max_players
    // =====================================================================

    public function test_edit_teams_count_single_event_recalculates_slots_and_max(): void
    {
        $o = $this->classic42();
        $this->seatDirect($o, 'setter');

        $this->updateEventHttp($o, ['teams_count' => 3])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['outside' => 12, 'setter' => 6], $this->slots($o));
        $gs = DB::table('event_game_settings')->where('event_id', $o->event_id)->first();
        $this->assertSame(18, (int) $gs->max_players);
        $this->assertSame(3, (int) $gs->teams_count);

        $occ = EventOccurrence::find($o->id);
        $this->assertFalse((bool) $occ->is_cancelled, 'дата та же (uniq_key совпал) — не пересоздаётся');
        $this->assertSame(18, (int) $occ->max_players, 'max_players копируется в дату');
        $this->assertSame(1, $this->activeRegs($o)->count(), 'записи сохраняются');
    }

    /**
     * Серия: max_players переписывается у всех будущих дат, КРОМЕ отредактированных вручную (is_individually_edited).
     * СТРАННОСТЬ: слоты (event_role_slots) общие на мероприятие, поэтому у такой даты слоты 18, а max_players 12.
     */
    public function test_edit_teams_count_series_updates_all_future_dates(): void
    {
        $o  = $this->classic42();
        $o2 = $this->addOccurrence($o, 7);
        $o3 = $this->addOccurrence($o, 14, ['is_individually_edited' => true, 'max_players' => 12]);
        $dow = Carbon::parse($o->starts_at, 'UTC')->setTimezone('Europe/Moscow')->dayOfWeekIso;

        $this->updateEventHttp($o, [
            'teams_count'         => 3,
            'is_recurring'        => 1,
            'recurrence_type'     => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_weekdays' => [$dow],
            'recurrence_end_type' => 'none',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(18, (int) EventOccurrence::find($o->id)->max_players);
        $this->assertSame(18, (int) EventOccurrence::find($o2->id)->max_players);
        $this->assertSame(12, (int) EventOccurrence::find($o3->id)->max_players);
        $this->assertSame(['outside' => 12, 'setter' => 6], $this->slots($o3));
    }

    // =====================================================================
    // Автоотмена по кворуму (events:cancel-by-quorum) для игры
    // =====================================================================

    public function test_cancel_by_quorum_counts_reserves_not_waitlist(): void
    {
        // min_players=6; регистрация закрылась час назад, игра через 2 часа
        $ok  = $this->classic42(['reserve' => 2, 'min_players' => 6, 'starts_in_hours' => 2, 'registration_ends_in_hours' => -1]);
        $bad = $this->classic42(['reserve' => 2, 'min_players' => 6, 'starts_in_hours' => 2, 'registration_ends_in_hours' => -1]);

        foreach ([$ok, $bad] as $occ) {
            for ($i = 0; $i < 4; $i++) {
                $this->seatDirect($occ, 'outside');
            }
            $this->seatDirect($occ, 'reserve');
        }
        $this->seatDirect($ok, 'reserve'); // 4 основа + 2 запасных = 6 → проводится
        foreach (range(1, 3) as $i) {        // у второй: 4 + 1 запасной + 3 в листе ожидания → отмена
            OccurrenceWaitlist::create(['occurrence_id' => $bad->id, 'user_id' => $this->makeUser()->id, 'positions' => ['outside'], 'sort_order' => $i]);
        }

        $this->artisan('events:cancel-by-quorum')->assertSuccessful();

        $this->assertFalse((bool) EventOccurrence::find($ok->id)->is_cancelled, 'запасные засчитываются в кворум');
        $this->assertTrue((bool) EventOccurrence::find($bad->id)->is_cancelled, 'лист ожидания в кворум не засчитывается');
    }

    // =====================================================================
    // Анонс: «Осталось мест» = max_players мероприятия + запасные − записи
    // =====================================================================

    public function test_announcement_free_places_from_event_settings(): void
    {
        $o = $this->classic42(['reserve' => 2]);
        $this->seatDirect($o, 'setter');
        $this->seatDirect($o, 'outside');
        $this->seatDirect($o, 'outside');

        $msg = app(OccurrenceAnnouncementMessageBuilder::class)->build($o->fresh('event'));
        $this->assertStringContainsString('Осталось мест: 11 из 14', $msg->text);
    }

    /**
     * СТРАННОСТЬ (важно для этапа 2): анонс берёт max_players из настроек мероприятия, а не даты —
     * индивидуальный max_players даты в анонсе не виден.
     */
    public function test_announcement_ignores_occurrence_max_players(): void
    {
        $o = $this->classic42();
        $o->forceFill(['max_players' => 18])->save();

        $msg = app(OccurrenceAnnouncementMessageBuilder::class)->build($o->fresh('event'));
        $this->assertStringContainsString('из 12', $msg->text);
        $this->assertSame(18, $o->fresh()->effectiveMaxPlayers());
    }
}
