<?php

namespace Tests\Feature;

use App\Models\BroadcastLink;
use App\Models\Event;
use App\Models\EventCourt;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Broadcast\BroadcastLinkService;
use App\Services\Broadcast\CourtStateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «▶ Матч начался» / «Отменить начало». dev-БД (pgsql), транзакция с откатом:
 * mv bootstrap/cache/config.php /tmp/c.bak; DB_CONNECTION=pgsql DB_DATABASE=volleyball SENTRY_LARAVEL_DSN= VIEW_COMPILED_PATH=/tmp/vc vendor/bin/phpunit tests/Feature/MatchStartTest.php; mv /tmp/c.bak bootstrap/cache/config.php
 */
class MatchStartTest extends TestCase
{
    use DatabaseTransactions;

    private TournamentMatch $match;
    private EventCourt $court;
    private Event $event;
    private User $owner;
    private User $scorer;
    private User $outsider;
    private BroadcastLink $link;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Нужна pgsql dev-БД (см. докблок).');
        }
        \Sentry\SentrySdk::getCurrentHub()->bindClient(\Sentry\ClientBuilder::create(['dsn' => null])->getClient());

        $row = DB::selectOne("select m.id from tournament_matches m join tournament_stages s on s.id=m.stage_id join events e on e.id=s.event_id
            where m.team_home_id is not null and m.team_away_id is not null and m.court_id is not null and e.format='tournament'
              and m.is_tiebreaker is not true order by m.id desc limit 1");
        $bots = User::where('is_bot', true)->whereNull('deleted_at')->orderBy('id')->limit(3)->get();
        if (!$row || $bots->count() < 3) {
            $this->markTestSkipped('Нет подходящего матча или ботов на dev.');
        }
        [$this->owner, $this->scorer, $this->outsider] = [$bots[0], $bots[1], $bots[2]];
        foreach ($bots as $b) {
            DB::table('users')->where('id', $b->id)->update(['role' => 'organizer', 'email_verified_at' => now()]);
        }

        $this->match = TournamentMatch::with('stage')->find($row->id);
        $this->event = Event::find($this->match->stage->event_id);
        $this->court = EventCourt::find($this->match->court_id);
        DB::table('events')->where('id', $this->event->id)->update(['organizer_id' => $this->owner->id]);
        $this->event->refresh();

        DB::table('event_tournament_settings')->where('event_id', $this->event->id)->update(['track_lineup' => false]);

        // чистое состояние: на площадке нет других live-матчей, в матче нет игры
        TournamentMatch::where('court_id', $this->court->id)->where('status', 'live')->update(['status' => 'scheduled']);
        foreach (['match_rally_events', 'match_lineup_events', 'match_set_starts'] as $t) {
            DB::table($t)->where('match_id', $this->match->id)->delete();
        }
        $this->match->update(['status' => 'scheduled', 'started_at' => null]);
        DB::table('broadcast_court_overrides')->where('court_id', $this->court->id)->delete();

        $this->link = app(BroadcastLinkService::class)->createScorer($this->owner->fresh(), $this->event, $this->court, $this->scorer);
        $this->link->update(['expires_at' => now()->addDay()]); // матч старый — срок по умолчанию уже в прошлом
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        return $this->actingAs($u);
    }

    private function state(): array
    {
        return app(CourtStateService::class)->forCourt($this->court->fresh());
    }

    private function scorerUrl(string $action): string
    {
        return "/s/{$this->link->token}/m/{$this->match->id}/{$action}";
    }

    public function test_start_makes_live_zero_zero_and_cancel_returns_splash(): void
    {
        $this->as($this->scorer)->postJson($this->scorerUrl('match-start'), ['client_request_id' => (string) Str::uuid()])
            ->assertOk()->assertJson(['success' => true]);

        $m = $this->match->fresh();
        $this->assertSame('live', $m->status);
        $this->assertNotNull($m->started_at);
        $st = $this->state();
        $this->assertSame('live', $st['mode']);
        $this->assertSame($m->id, $st['match']['id'] ?? null);

        $this->as($this->scorer)->postJson($this->scorerUrl('match-unstart'))->assertOk();
        $m = $this->match->fresh();
        $this->assertSame('scheduled', $m->status);
        $this->assertNull($m->started_at);
        $this->assertSame('splash', $this->state()['mode']);
    }

    public function test_start_is_idempotent_and_stale_duplicate_after_cancel_is_ignored(): void
    {
        $rid = (string) Str::uuid();
        $this->as($this->scorer)->postJson($this->scorerUrl('match-start'), ['client_request_id' => $rid])->assertOk();
        $first = $this->match->fresh()->started_at;
        $this->as($this->scorer)->postJson($this->scorerUrl('match-start'), ['client_request_id' => $rid])->assertOk();
        $this->assertEquals($first, $this->match->fresh()->started_at);

        $this->as($this->scorer)->postJson($this->scorerUrl('match-unstart'))->assertOk();
        // запоздалый повтор того же запроса не должен снова запустить матч
        $this->as($this->scorer)->postJson($this->scorerUrl('match-start'), ['client_request_id' => $rid])->assertOk();
        $this->assertSame('scheduled', $this->match->fresh()->status);
        // двойная отмена — тоже успех
        $this->as($this->scorer)->postJson($this->scorerUrl('match-unstart'))->assertOk();
    }

    public function test_start_releases_manual_splash(): void
    {
        DB::table('broadcast_court_overrides')->insert(['court_id' => $this->court->id, 'manual_mode' => 'splash', 'manual_mode_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->as($this->scorer)->postJson($this->scorerUrl('match-start'))->assertOk();
        $this->assertSame('live', $this->state()['mode']);
    }

    public function test_cancel_unavailable_once_there_are_points(): void
    {
        $this->as($this->scorer)->postJson($this->scorerUrl('match-start'))->assertOk();
        $this->as($this->scorer)->postJson($this->scorerUrl('point'), [
            'set_number' => 1, 'team_id' => $this->match->team_home_id, 'action_type' => 'unattributed', 'client_request_id' => (string) Str::uuid(),
        ])->assertOk();
        $this->as($this->scorer)->postJson($this->scorerUrl('match-unstart'))->assertStatus(422);
        $this->assertSame('live', $this->match->fresh()->status);
    }

    public function test_organizer_can_start_and_cancel(): void
    {
        $this->as($this->owner)->postJson("/tournament-matches/{$this->match->id}/score/rally/match-start")->assertOk();
        $this->assertSame('live', $this->match->fresh()->status);
        $this->as($this->owner)->postJson("/tournament-matches/{$this->match->id}/score/rally/match-unstart")->assertOk();
        $this->assertSame('scheduled', $this->match->fresh()->status);
    }

    public function test_outsider_cannot(): void
    {
        // чужой аккаунт на именной ссылке ведущего
        $this->as($this->outsider)->postJson($this->scorerUrl('match-start'))->assertStatus(403);
        // посторонний организатор на роуте организатора
        $this->as($this->outsider)->postJson("/tournament-matches/{$this->match->id}/score/rally/match-start")->assertForbidden();
        $this->assertSame('scheduled', $this->match->fresh()->status);
    }

    public function test_button_visibility_in_fragment(): void
    {
        $frag = fn () => $this->as($this->scorer)->getJson("/s/{$this->link->token}/m/{$this->match->id}/fragment")->assertOk()->json('html');

        $html = $frag();
        $this->assertStringContainsString('js-rw-match-start', $html);
        $this->assertStringNotContainsString('js-rw-match-unstart', $html);

        $this->as($this->scorer)->postJson($this->scorerUrl('match-start'))->assertOk();
        $html = $frag();
        $this->assertStringNotContainsString('js-rw-match-start', $html);
        $this->assertStringContainsString('js-rw-match-unstart', $html);

        // при учёте состава роль кнопки играет «Начать партию»
        $this->as($this->scorer)->postJson($this->scorerUrl('match-unstart'))->assertOk();
        DB::table('event_tournament_settings')->where('event_id', $this->event->id)->update(['track_lineup' => true]);
        $html = $frag();
        $this->assertStringNotContainsString('js-rw-match-start', $html);
    }
}
