<?php

namespace Tests\Feature;

use App\Jobs\NotifyOrganizerQuorumExpandedJob;
use App\Jobs\RefreshOccurrenceAnnouncementJob;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\OccurrenceWaitlist;
use App\Models\User;
use App\Services\AutoQuorumService;
use App\Services\WaitlistService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

    // =====================================================================
    // Расширение (AutoQuorumService::maybeExpand и точки вызова)
    // =====================================================================

    private function aqGame(array $o = []): EventOccurrence
    {
        return $this->classic42(array_merge(['aq' => ['enabled' => 1, 'per' => 5, 'max' => 4]], $o));
    }

    private function wl(EventOccurrence $o, array $positions = ['outside']): User
    {
        $u = $this->makeUser();
        app(WaitlistService::class)->join($o->fresh(['event.gameSettings']), $u, $positions);

        return $u;
    }

    private function teams(EventOccurrence $o): ?int
    {
        $v = DB::table('event_occurrences')->where('id', $o->id)->value('quorum_teams_count');

        return $v === null ? null : (int) $v;
    }

    public function test_classic_expands_by_steps_15_and_20(): void
    {
        $o = $this->aqGame();
        $this->fillMain($o);                       // 12
        $this->wl($o);
        $this->wl($o);                             // N = 14
        $this->assertNull($this->teams($o), '14 записавшихся — не расширяем');

        $u15 = $this->wl($o, ['outside']);         // N = 15 → 3 команды
        $this->assertSame(3, $this->teams($o));
        $this->assertNotNull(DB::table('event_occurrences')->where('id', $o->id)->value('quorum_expanded_at'));
        $this->assertSame(18, $o->fresh()->effectiveMaxPlayers());
        $this->assertTrue($this->isActive($o, $u15), 'из листа ожидания переведён на новое место outside');
        $this->assertSame(15, $this->activeRegs($o)->count(), 'все трое из листа ожидания — в основе');

        for ($i = 0; $i < 4; $i++) {
            $this->wl($o, ['setter']);             // N = 19
        }
        $this->assertSame(3, $this->teams($o));
        $this->wl($o, ['setter']);                 // N = 20 → 4 команды
        $this->assertSame(4, $this->teams($o));
        $this->assertSame(24, $o->fresh()->effectiveMaxPlayers());

        $log = DB::table('event_occurrence_quorum_log')->where('occurrence_id', $o->id)->orderBy('id')->get();
        $this->assertSame([[2, 3, 15], [3, 4, 20]], $log->map(fn ($r) => [(int) $r->from_teams, (int) $r->to_teams, (int) $r->total_registered])->all());
        $this->assertContains($u15->id, json_decode($log[0]->promoted_user_ids, true));
    }

    public function test_max_teams_caps_expansion(): void
    {
        $o = $this->aqGame(['aq' => ['enabled' => 1, 'per' => 5, 'max' => 3]]);
        $this->fillMain($o);
        for ($i = 0; $i < 8; $i++) {
            $this->wl($o);                         // N = 20
        }
        $this->assertSame(3, $this->teams($o));
    }

    public function test_promotion_by_roles_unmatched_stay_in_waitlist(): void
    {
        $o = $this->aqGame();
        $this->fillMain($o);
        $s1 = $this->wl($o, ['setter']);
        $m1 = $this->wl($o, ['middle']);           // такой роли в 4×2 нет
        $s2 = $this->wl($o, ['setter']);           // N = 15 → +1 команда: setter +2, outside +4
        $this->assertSame(3, $this->teams($o));
        $this->assertTrue($this->isActive($o, $s1));
        $this->assertTrue($this->isActive($o, $s2));
        $this->assertFalse($this->isActive($o, $m1));
        $this->assertTrue($this->inWaitlist($o, $m1));
        $this->assertSame([$s1->id, $s2->id], collect(json_decode(DB::table('event_occurrence_quorum_log')->where('occurrence_id', $o->id)->value('promoted_user_ids'), true))->sort()->values()->all());
    }

    public function test_beach_two_pairs_expand_to_three_at_six(): void
    {
        $o = $this->beach2x2(['aq' => ['enabled' => 1, 'max' => 4]]); // perTeam по умолчанию для пляжа = 2
        $this->fillMain($o);                       // 4
        $this->wl($o, []);                         // 5
        $this->assertNull($this->teams($o));
        $u = $this->wl($o, []);                    // 6 → 3 пары
        $this->assertSame(3, $this->teams($o));
        $this->assertSame(6, $this->activeRegs($o)->count());
        $this->assertTrue($this->isActive($o, $u));
    }

    public function test_beach_by_roles_adds_one_blocker_and_one_defender(): void
    {
        $o = $this->beach2x2Roles(['aq' => ['enabled' => 1, 'max' => 4]]);
        $this->fillMain($o);
        $b1 = $this->wl($o, ['blocker']);
        $b2 = $this->wl($o, ['blocker']);          // N = 6 → 3 пары: +1 blocker +1 defender
        $this->assertSame(3, $this->teams($o));
        $this->assertTrue($this->isActive($o, $b1));
        $this->assertFalse($this->isActive($o, $b2), 'второй блокирующий — места blocker кончились');
        $d = $this->wl($o, ['defender']);
        $this->assertTrue($this->isActive($o, $d), 'новый defender свободен');
        $this->assertSame(['blocker' => 3, 'defender' => 3], app(\App\Services\OccurrenceLimitService::class)->roleLimits($o->fresh(['event.gameSettings'])));
    }

    public function test_cancellations_do_not_shrink(): void
    {
        $o = $this->aqGame();
        $regs = $this->fillMain($o);
        for ($i = 0; $i < 3; $i++) {
            $this->wl($o);
        }
        $this->assertSame(3, $this->teams($o));
        foreach (array_slice($regs, 0, 6) as $r) {
            $this->cancelReg($r);
        }
        app(AutoQuorumService::class)->maybeExpand($o);
        $this->assertSame(3, $this->teams($o));
        $this->assertSame(18, $o->fresh()->effectiveMaxPlayers());
    }

    public function test_no_expansion_when_disabled_started_or_registration_closed(): void
    {
        $off = $this->classic42(['aq' => ['enabled' => 0, 'per' => 5, 'max' => 4]]);
        $closed = $this->aqGame(['registration_ends_in_hours' => -1]);
        $started = $this->aqGame(['starts_in_hours' => 72]);
        foreach ([$off, $closed, $started] as $o) {
            $this->fillMain($o);
            for ($i = 0; $i < 3; $i++) {
                OccurrenceWaitlist::create(['occurrence_id' => $o->id, 'user_id' => $this->makeUser()->id, 'positions' => ['outside'], 'sort_order' => $i + 1]);
            }
        }
        DB::table('event_occurrences')->where('id', $started->id)->update(['starts_at' => now('UTC')->subMinute()]);

        foreach ([$off, $closed, $started] as $o) {
            $this->assertNull(app(AutoQuorumService::class)->maybeExpand($o));
            $this->assertNull($this->teams($o));
        }
    }

    public function test_no_expansion_in_team_or_mixed_group_modes(): void
    {
        foreach ([['classic', 'team'], ['beach', 'mixed_group']] as [$dir, $mode]) {
            $o = $dir === 'classic' ? $this->aqGame() : $this->beach2x2(['aq' => ['enabled' => 1, 'max' => 4]]);
            $this->fillMain($o);
            DB::table('events')->where('id', $o->event_id)->update(['registration_mode' => $mode]);
            for ($i = 0; $i < 4; $i++) {
                OccurrenceWaitlist::create(['occurrence_id' => $o->id, 'user_id' => $this->makeUser()->id, 'positions' => [], 'sort_order' => $i + 1]);
            }
            $this->assertNull(app(AutoQuorumService::class)->maybeExpand($o), $mode);
        }
    }

    public function test_repeated_calls_expand_once(): void
    {
        $o = $this->aqGame();
        $this->fillMain($o);
        for ($i = 0; $i < 3; $i++) {
            OccurrenceWaitlist::create(['occurrence_id' => $o->id, 'user_id' => $this->makeUser()->id, 'positions' => ['middle'], 'sort_order' => $i + 1]);
        }
        $svc = app(AutoQuorumService::class);
        $this->assertSame(3, $svc->maybeExpand($o)['to']);
        $this->assertNull($svc->maybeExpand($o));
        $this->assertNull($svc->maybeExpand($o));
        $this->assertSame(1, DB::table('event_occurrence_quorum_log')->where('occurrence_id', $o->id)->count());
    }

    public function test_conditional_update_loses_race_gracefully(): void
    {
        $o = $this->aqGame();
        $this->fillMain($o);
        for ($i = 0; $i < 3; $i++) {
            OccurrenceWaitlist::create(['occurrence_id' => $o->id, 'user_id' => $this->makeUser()->id, 'positions' => ['middle'], 'sort_order' => $i + 1]);
        }
        // «соседний процесс» уже расширил до 3 между чтением и записью — повтор ничего не делает
        DB::table('event_occurrences')->where('id', $o->id)->update(['quorum_teams_count' => 3]);
        $this->assertNull(app(AutoQuorumService::class)->maybeExpand($o));
        $this->assertSame(0, DB::table('event_occurrence_quorum_log')->where('occurrence_id', $o->id)->count());
    }

    public function test_notification_announcement_and_log(): void
    {
        $o = $this->aqGame();
        $this->fillMain($o);
        Queue::fake();
        for ($i = 0; $i < 3; $i++) {
            $this->wl($o);
        }
        Queue::assertPushed(NotifyOrganizerQuorumExpandedJob::class, fn ($j) => $j->occurrenceId === $o->id && $j->fromTeams === 2 && $j->toTeams === 3);
        Queue::assertPushed(RefreshOccurrenceAnnouncementJob::class, fn ($j) => $j->occurrenceId === $o->id);

        // джоба уведомления создаёт event_quorum_expanded организатору
        (new NotifyOrganizerQuorumExpandedJob($o->id, 2, 3, 3))->handle(app(\App\Services\UserNotificationService::class));
        $n = DB::table('user_notifications')->where('user_id', $this->organizer()->id)->where('type', 'event_quorum_expanded')->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('расширена до 3 команд — проверьте площадку', $n->title);
    }

    public function test_gender_quota_scales_with_date_teams(): void
    {
        $o = $this->aqGame(['gender' => [
            'game_gender_policy' => 'mixed_limited', 'game_gender_limited_side' => 'female',
            'game_gender_limited_max' => 2, 'game_gender_limited_positions' => ['outside'],
        ]]);
        $f = fn () => $this->makeUser(['gender' => 'f']);
        $this->seatDirect($o, 'outside', $f());
        $this->seatDirect($o, 'outside', $f());
        $this->joinHttp($f(), $o, 'outside')->assertStatus(422); // квота 2 из 2

        DB::table('event_occurrences')->where('id', $o->id)->update(['quorum_teams_count' => 3]);
        $this->assertSame(3, app(\App\Services\OccurrenceLimitService::class)->genderLimitFor($o->fresh(['event.gameSettings']), 2));
        $this->joinHttp($f(), $o, 'outside')->assertOk();          // floor(2/2×3) = 3
        $this->joinHttp($f(), $o, 'outside')->assertStatus(422);
    }

    public function test_sweep_expands_missed_date(): void
    {
        $o = $this->aqGame();
        $this->fillMain($o);
        for ($i = 0; $i < 3; $i++) {   // в обход точек вызова
            OccurrenceWaitlist::create(['occurrence_id' => $o->id, 'user_id' => $this->makeUser()->id, 'positions' => ['outside'], 'sort_order' => $i + 1]);
        }
        $this->assertNull($this->teams($o));
        $this->artisan('auto-quorum:sweep')->assertSuccessful();
        $this->assertSame(3, $this->teams($o));
        $this->assertSame(15, $this->activeRegs($o)->count());
    }

    public function test_http_join_triggers_expansion(): void
    {
        $o = $this->aqGame(['reserve' => 3]);
        $this->fillMain($o);
        $this->joinHttp($this->makeUser(), $o, 'reserve')->assertOk();
        $this->joinHttp($this->makeUser(), $o, 'reserve')->assertOk();
        $this->assertNull($this->teams($o));
        $this->joinHttp($this->makeUser(), $o, 'reserve')->assertOk();   // 12 + 3 запасных = 15
        $this->assertSame(3, $this->teams($o));
        $this->assertSame(3, $this->activeRegs($o)->where('position', 'reserve')->count(), 'запасные остаются запасными');
    }

    public function test_organizer_manual_add_triggers_expansion(): void
    {
        $o = $this->aqGame(['reserve' => 3]);
        $this->fillMain($o);
        $this->wl($o, ['middle']);
        $this->wl($o, ['middle']);                 // N = 14
        $this->actingAs($this->organizer())
            ->post("/events/{$o->event_id}/registrations/add", ['user_id' => $this->makeUser()->id, 'position' => 'reserve', 'occurrence_id' => $o->id])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame(3, $this->teams($o), 'ручное добавление 15-го в запасные — расширение');
    }

    public function test_enabling_on_existing_game_expands_on_save(): void
    {
        $o = $this->classic42();
        $this->fillMain($o);
        for ($i = 0; $i < 3; $i++) {
            OccurrenceWaitlist::create(['occurrence_id' => $o->id, 'user_id' => $this->makeUser()->id, 'positions' => ['outside'], 'sort_order' => $i + 1]);
        }
        $this->updateEventHttp($o, ['teams_count' => 2, 'auto_quorum_enabled' => 1, 'auto_quorum_max_teams' => 3])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, $this->teams($o));
        $this->assertTrue((bool) DB::table('event_game_settings')->where('event_id', $o->event_id)->value('auto_quorum_enabled'));
    }

    public function test_disabling_does_not_collapse_expanded_dates(): void
    {
        $o = $this->aqGame();
        $this->fillMain($o);
        for ($i = 0; $i < 3; $i++) {
            $this->wl($o);
        }
        $this->assertSame(3, $this->teams($o));
        $this->updateEventHttp($o, ['teams_count' => 2, 'auto_quorum_enabled' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, $this->teams($o));
        $this->assertSame(18, $o->fresh()->effectiveMaxPlayers());
    }

    // =====================================================================
    // Валидация
    // =====================================================================

    public function test_validation_max_less_than_teams(): void
    {
        $o = $this->classic42(['teams' => 3]);
        $this->updateEventHttp($o, ['teams_count' => 3, 'auto_quorum_enabled' => 1, 'auto_quorum_max_teams' => 2])
            ->assertSessionHasErrors('auto_quorum_max_teams');
        $this->assertFalse((bool) DB::table('event_game_settings')->where('event_id', $o->event_id)->value('auto_quorum_enabled'));
    }

    public function test_validation_not_with_statistics(): void
    {
        $o = $this->classic42();
        $this->updateEventHttp($o, ['teams_count' => 2, 'collect_stats' => 1, 'auto_quorum_enabled' => 1, 'auto_quorum_max_teams' => 3])
            ->assertSessionHasErrors('auto_quorum_enabled');
    }

    public function test_validation_max_below_already_expanded_date(): void
    {
        $o = $this->aqGame();
        DB::table('event_occurrences')->where('id', $o->id)->update(['quorum_teams_count' => 4]);
        $this->updateEventHttp($o, ['teams_count' => 2, 'auto_quorum_enabled' => 1, 'auto_quorum_max_teams' => 3])
            ->assertSessionHasErrors('auto_quorum_max_teams');
        $this->updateEventHttp($o, ['teams_count' => 2, 'auto_quorum_enabled' => 1, 'auto_quorum_max_teams' => 4])
            ->assertSessionHasNoErrors();
    }

    public function test_validation_format_and_mode_for_create(): void
    {
        $svc = app(AutoQuorumService::class);
        $on = ['auto_quorum_enabled' => 1, 'auto_quorum_max_teams' => 3, 'teams_count' => 2];
        $this->assertSame([], $svc->validateSettings($on, 'game', 'classic', 'single'));
        $this->assertSame([], $svc->validateSettings($on, 'training_game', 'beach', 'single'));
        $this->assertArrayHasKey('auto_quorum_enabled', $svc->validateSettings($on, 'tournament', 'classic', 'single'));
        $this->assertArrayHasKey('auto_quorum_enabled', $svc->validateSettings($on, 'master_class', 'classic', 'single'));
        $this->assertArrayHasKey('auto_quorum_enabled', $svc->validateSettings($on, 'game', 'classic', 'team'));
        $this->assertArrayHasKey('auto_quorum_enabled', $svc->validateSettings($on, 'game', 'beach', 'mixed_group'));
        $this->assertArrayHasKey('auto_quorum_max_teams', $svc->validateSettings(['auto_quorum_enabled' => 1, 'teams_count' => 2], 'game', 'classic', 'single'));
        $this->assertSame([], $svc->validateSettings(['auto_quorum_enabled' => 0, 'collect_stats' => 1], 'tournament', 'classic', 'team'));
    }

    // =====================================================================
    // Страницы: форма создания/редактирования, управление записями
    // =====================================================================

    public function test_management_page_shows_expanded_badge(): void
    {
        $o = $this->aqGame();
        $this->fillMain($o);
        for ($i = 0; $i < 3; $i++) {
            $this->wl($o);
        }
        $this->actingAs($this->organizer())->get("/events/{$o->event_id}/registrations?occurrence={$o->id}")
            ->assertOk()->assertSee('Расширено до 3 команд')->assertSee('3/18', false); // 15 записано из 18
    }

    public function test_edit_form_shows_iphone_toggle_for_games_only(): void
    {
        $o = $this->aqGame();
        $this->actingAs($this->organizer())->get("/events/create/event_management/{$o->event_id}/edit")
            ->assertOk()
            ->assertSee('id="aq_switch"', false)
            ->assertSee('up-switch is-on', false)
            ->assertSee('Авто увеличение кворума');

        DB::table('events')->where('id', $o->event_id)->update(['registration_mode' => 'team']);
        $this->actingAs($this->organizer())->get("/events/create/event_management/{$o->event_id}/edit")
            ->assertOk()->assertDontSee('id="aq_switch"', false);
    }

    public function test_create_form_has_toggle_after_registration_mode_hint(): void
    {
        $html = $this->actingAs($this->organizer())->get('/events/create')->assertOk()->getContent();
        $hint = mb_strpos($html, __('events.reg_mode_hint_classic'));
        $tgl  = mb_strpos($html, 'id="aq_switch"');
        $this->assertNotFalse($hint);
        $this->assertNotFalse($tgl);
        $this->assertGreaterThan($hint, $tgl, 'тумблер — сразу после подсказки о режиме регистрации');
        $this->assertStringContainsString('data-show-if="format=game|training_game,registration_mode=single"', $html);
        $this->assertStringContainsString('data-hide-if="collect_stats=1"', $html);
    }
}
