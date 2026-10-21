<?php

namespace Tests\Feature;

use App\Models\BroadcastDestination;
use App\Models\BroadcastLink;
use App\Models\Event;
use App\Models\EventCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Работает на dev-БД (pgsql) в транзакции с откатом: DB_CONNECTION=pgsql DB_DATABASE=volleyball vendor/bin/phpunit tests/Feature/BroadcastDestinationsTest.php
 * Берёт существующее турнирное мероприятие (кроме ZZ_BC2), в транзакции меняет владельца и дату — всё откатывается.
 */
class BroadcastDestinationsTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY = 'SECRETKEY-1234567890-zz';

    private Event $event;
    private User $owner;
    private User $staff;
    private User $outsider;
    private EventCourt $court;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Нужна pgsql dev-БД (см. докблок).');
        }
        \Sentry\SentrySdk::getCurrentHub()->bindClient(\Sentry\ClientBuilder::create(['dsn' => null])->getClient());

        $eventId = DB::table('events')->where('format', 'tournament')->where('title', 'not like', 'ZZ_BC2%')->orderByDesc('id')->value('id');
        $bots = User::where('is_bot', true)->whereNull('deleted_at')->orderBy('id')->limit(3)->get();
        if (!$eventId || $bots->count() < 3) {
            $this->markTestSkipped('Нет турнирного мероприятия или ботов на dev.');
        }
        [$this->owner, $this->staff, $this->outsider] = [$bots[0], $bots[1], $bots[2]];
        foreach ($bots as $b) {
            DB::table('users')->where('id', $b->id)->update(['role' => 'organizer', 'email_verified_at' => now()]);
        }
        DB::table('events')->where('id', $eventId)->update(['organizer_id' => $this->owner->id, 'starts_at' => now()->addHour()]);
        DB::table('organizer_staff')->insert(['organizer_id' => $this->owner->id, 'staff_user_id' => $this->staff->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('organizer_staff')->where('staff_user_id', $this->outsider->id)->delete();

        $this->event = Event::find($eventId);
        $this->court = EventCourt::create(['event_id' => $this->event->id, 'occurrence_id' => null, 'name' => 'ZZ_TEST court', 'is_active' => true]);
        foreach ([$this->owner, $this->staff, $this->outsider] as $u) {
            $u->refresh();
        }
    }

    /** Смена пользователя между запросами: иначе сессия/гард предыдущего (hash пароля) разлогинивает нового. */
    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        return $this->actingAs($u);
    }

    private function base(): string
    {
        return "/events/{$this->event->id}/broadcast/courts/{$this->court->id}/destinations";
    }

    private function add(array $over = [], ?User $as = null)
    {
        return $this->as($as ?? $this->owner)->postJson($this->base(), $over + [
            'platform' => 'vk', 'server_url' => 'rtmp://ovsu.mycdn.me/input/', 'stream_key' => self::KEY,
        ]);
    }

    public function test_key_is_encrypted_in_db(): void
    {
        $this->add()->assertOk()->assertJsonMissing(['stream_key' => self::KEY]);
        $raw = DB::table('broadcast_destinations')->where('court_id', $this->court->id)->first();
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString(self::KEY, $raw->stream_key);
        $this->assertSame(self::KEY, BroadcastDestination::find($raw->id)->stream_key);
        $this->assertTrue((bool) $raw->is_default, 'первое назначение — по умолчанию');
    }

    public function test_outsider_gets_403_everywhere_staff_ok(): void
    {
        $id = $this->add()->json('destination.id');
        $b = $this->base();
        $o = $this->outsider;
        $this->as($o)->get("/events/{$this->event->id}/broadcast")->assertForbidden();
        $this->add([], $o)->assertForbidden();
        $this->as($o)->postJson("$b/$id/reveal")->assertForbidden();
        $this->as($o)->postJson("$b/$id/default")->assertForbidden();
        $this->as($o)->deleteJson("$b/$id")->assertForbidden();
        $this->as($o)->postJson("/broadcast/camera/{$this->court->id}/start")->assertForbidden();

        $this->as($this->staff)->get("/events/{$this->event->id}/broadcast")->assertOk();
        $this->as($this->staff)->postJson("$b/$id/reveal")->assertOk()->assertJson(['stream_key' => self::KEY]);
        $this->as($this->staff)->postJson("/broadcast/camera/{$this->court->id}/start")->assertOk();
    }

    public function test_key_not_in_html_of_pages(): void
    {
        $this->add();
        $this->assertStringNotContainsString(self::KEY, $this->as($this->owner)->get("/events/{$this->event->id}/broadcast")->getContent());
        $this->as($this->owner)->postJson("/broadcast/camera/{$this->court->id}/start")->assertOk();
        $html = $this->as($this->owner)->get('/broadcast/camera')->assertOk()->getContent();
        $this->assertStringContainsString('ZZ_TEST court', $html);
        $this->assertStringNotContainsString(self::KEY, $html);
        $this->assertStringNotContainsString('ovsu.mycdn.me', $html, 'адрес сервера на экране камеры не нужен');
    }

    public function test_camera_hides_courts_from_outsider(): void
    {
        $this->as($this->owner)->postJson("/broadcast/camera/{$this->court->id}/start")->assertStatus(422);
        $this->add();
        $this->as($this->owner)->postJson("/broadcast/camera/{$this->court->id}/start")->assertOk();
        $this->assertStringNotContainsString('ZZ_TEST court', $this->as($this->outsider)->get('/broadcast/camera')->getContent());
    }

    public function test_start_without_destination_is_clear_error(): void
    {
        $r = $this->as($this->owner)->postJson("/broadcast/camera/{$this->court->id}/start");
        $r->assertStatus(422)->assertJson(['ok' => false, 'code' => 'no_destination']);
        $this->assertNotEmpty($r->json('message'));
        $this->assertStringContainsString("/events/{$this->event->id}/broadcast", $r->json('setup_url'));
    }

    public function test_start_returns_payload_and_issues_overlay_link(): void
    {
        $this->add(['platform' => 'rutube', 'server_url' => 'rtmps://live.example.ru/live']);
        $this->assertSame(0, BroadcastLink::where('court_id', $this->court->id)->count());
        $r = $this->as($this->owner)->postJson("/broadcast/camera/{$this->court->id}/start")->assertOk();
        $r->assertJson(['ok' => true, 'server_url' => 'rtmps://live.example.ru/live', 'stream_key' => self::KEY, 'court_name' => 'ZZ_TEST court']);
        $this->assertStringContainsString('/o/', $r->json('overlay_url'));
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
        $this->assertSame(1, BroadcastLink::where('court_id', $this->court->id)->whereNull('revoked_at')->count());
        // повторный старт не плодит ссылки
        $this->as($this->owner)->postJson("/broadcast/camera/{$this->court->id}/start")->assertOk();
        $this->assertSame(1, BroadcastLink::where('court_id', $this->court->id)->whereNull('revoked_at')->count());
    }

    public function test_server_validation_rtmp_rtmps_only(): void
    {
        foreach (['http://x.ru/live', 'https://x.ru/live', 'ftp://x.ru', 'rtmp://', 'x.ru/live', 'rtmp://a b/c', ''] as $bad) {
            $this->add(['server_url' => $bad])->assertStatus(422);
        }
        $this->add(['server_url' => 'rtmp://x.ru:1935/live'])->assertOk();
        $this->add(['server_url' => 'RTMPS://x.ru/live'])->assertOk();
        $this->assertSame(2, BroadcastDestination::where('court_id', $this->court->id)->count());
    }

    public function test_youtube_server_autofill_and_default_switch(): void
    {
        $a = $this->add(['platform' => 'youtube', 'server_url' => ''])->assertOk();
        $this->assertSame(BroadcastDestination::YOUTUBE_SERVER, $a->json('destination.server_url'));
        $b = $this->add(['is_default' => true])->assertOk();
        $this->assertSame(1, BroadcastDestination::where('court_id', $this->court->id)->where('is_default', true)->count());
        $this->assertTrue(BroadcastDestination::find($b->json('destination.id'))->is_default);
        // удаление умолчательного → умолчанием становится оставшийся
        $this->as($this->owner)->deleteJson($this->base() . '/' . $b->json('destination.id'))->assertOk();
        $this->assertTrue(BroadcastDestination::find($a->json('destination.id'))->is_default);
    }

    public function test_destination_of_other_court_is_404(): void
    {
        $other = EventCourt::create(['event_id' => $this->event->id, 'occurrence_id' => null, 'name' => 'ZZ_TEST other', 'is_active' => true]);
        $id = $this->add()->json('destination.id');
        $this->as($this->owner)->postJson("/events/{$this->event->id}/broadcast/courts/{$other->id}/destinations/$id/reveal")->assertNotFound();
    }
}
