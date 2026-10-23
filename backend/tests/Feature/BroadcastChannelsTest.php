<?php

namespace Tests\Feature;

use App\Models\BroadcastChannel;
use App\Models\BroadcastDestination;
use App\Models\Event;
use App\Models\EventCourt;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * dev-БД (pgsql), транзакция с откатом:
 * DB_CONNECTION=pgsql DB_DATABASE=volleyball SENTRY_LARAVEL_DSN= vendor/bin/phpunit tests/Feature/BroadcastChannelsTest.php
 */
class BroadcastChannelsTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY = 'CHANNELKEY-9876543210-zz';
    private const LEGACY = 'LEGACYKEY-1111111111-zz';

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
        DB::table('broadcast_channels')->whereIn('organizer_id', $bots->pluck('id'))->delete();

        $this->event = Event::find($eventId);
        $this->court = EventCourt::create(['event_id' => $this->event->id, 'occurrence_id' => null, 'name' => 'ZZ_TEST court', 'is_active' => true]);
        foreach ([$this->owner, $this->staff, $this->outsider] as $u) {
            $u->refresh();
        }
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        return $this->actingAs($u);
    }

    private function addChannel(array $over = [], ?User $as = null)
    {
        return $this->as($as ?? $this->owner)->postJson('/broadcast/channels', $over + [
            'platform' => 'vk', 'label' => 'ZZ_CH', 'server_url' => 'rtmp://ovsu.mycdn.me/input/', 'stream_key' => self::KEY,
        ]);
    }

    private function select(?int $channelId, ?User $as = null)
    {
        return $this->as($as ?? $this->owner)->postJson("/events/{$this->event->id}/broadcast/courts/{$this->court->id}/channel", ['channel_id' => $channelId]);
    }

    private function start(?User $as = null)
    {
        return $this->as($as ?? $this->owner)->postJson("/broadcast/camera/{$this->court->id}/start");
    }

    public function test_key_encrypted_in_db_and_first_channel_is_default(): void
    {
        $r = $this->addChannel()->assertOk()->assertJsonMissing(['stream_key' => self::KEY]);
        $raw = DB::table('broadcast_channels')->where('id', $r->json('channel.id'))->first();
        $this->assertStringNotContainsString(self::KEY, $raw->stream_key);
        $this->assertSame(self::KEY, BroadcastChannel::find($raw->id)->stream_key);
        $this->assertTrue((bool) $raw->is_default);
        $this->assertFalse((bool) BroadcastChannel::find($this->addChannel(['label' => 'second'])->json('channel.id'))->is_default);
    }

    public function test_only_owner_and_admin_manage_channels(): void
    {
        $id = $this->addChannel()->json('channel.id');
        foreach ([$this->staff, $this->outsider] as $u) {
            $this->addChannel([], $u)->assertOk(); // свой канал создать может любой организатор…
            $this->as($u)->postJson("/broadcast/channels/$id/reveal")->assertForbidden();
            $this->as($u)->patchJson("/broadcast/channels/$id", ['platform' => 'vk', 'server_url' => 'rtmp://x.ru/a'])->assertForbidden();
            $this->as($u)->postJson("/broadcast/channels/$id/default")->assertForbidden();
            $this->as($u)->deleteJson("/broadcast/channels/$id")->assertForbidden();
        }
        $this->as($this->owner)->postJson("/broadcast/channels/$id/reveal")->assertOk()->assertJson(['stream_key' => self::KEY]);
        $admin = User::where('role', 'admin')->whereNull('deleted_at')->first();
        if ($admin) {
            $this->as($admin)->postJson("/broadcast/channels/$id/reveal")->assertOk();
        }
        // обычный игрок не заходит на страницу вообще
        DB::table('users')->where('id', $this->outsider->id)->update(['role' => 'user']);
        $this->as($this->outsider->refresh())->get('/broadcast/channels')->assertForbidden();
    }

    public function test_staff_sees_channel_names_but_never_key_or_server(): void
    {
        $id = $this->addChannel()->json('channel.id');
        $this->select($id)->assertOk();

        foreach ([$this->staff, $this->owner] as $u) {
            $html = $this->as($u)->get("/events/{$this->event->id}/broadcast")->assertOk()->getContent();
            $this->assertStringContainsString('ZZ_CH', $html);
            $this->assertStringNotContainsString(self::KEY, $html);
        }
        $staffHtml = $this->as($this->staff)->get("/events/{$this->event->id}/broadcast")->getContent();
        $this->assertStringNotContainsString('ovsu.mycdn.me', $staffHtml, 'сервер канала помощнику не показываем');

        $camera = $this->as($this->staff)->get('/broadcast/camera')->getContent();
        $this->assertStringNotContainsString(self::KEY, $camera);
        $this->assertStringNotContainsString('ovsu.mycdn.me', $camera);

        // reveal назначения-канала: помощнику 403, владельцу ключ
        $destId = DB::table('broadcast_destinations')->where('court_id', $this->court->id)->value('id');
        $u = "/events/{$this->event->id}/broadcast/courts/{$this->court->id}/destinations/$destId/reveal";
        $this->as($this->staff)->postJson($u)->assertForbidden();
        $this->as($this->owner)->postJson($u)->assertOk()->assertJson(['stream_key' => self::KEY]);

        // страница каналов у помощника с ключом не отдаётся нигде, а /start — единственное место, где он его получает
        $this->start($this->staff)->assertOk()->assertJson(['stream_key' => self::KEY, 'server_url' => 'rtmp://ovsu.mycdn.me/input/']);
    }

    public function test_staff_can_select_channel_of_event_owner_only(): void
    {
        $mine = $this->addChannel()->json('channel.id');
        $foreign = $this->addChannel([], $this->outsider)->json('channel.id');
        $this->select($mine, $this->staff)->assertOk();
        $this->select($foreign, $this->staff)->assertStatus(422);
        $this->select($mine, $this->outsider)->assertForbidden();
        $this->assertSame(1, BroadcastDestination::where('court_id', $this->court->id)->count());
        $this->select($mine)->assertOk(); // повторный выбор не плодит строки
        $this->assertSame(1, BroadcastDestination::where('court_id', $this->court->id)->count());
    }

    public function test_start_with_channel_default_channel_and_one_time_key(): void
    {
        // ничего → понятная ошибка
        $this->start()->assertStatus(422)->assertJson(['code' => 'no_destination']);

        // канал по умолчанию, у площадки ничего не выбрано
        $id = $this->addChannel()->json('channel.id');
        $this->start()->assertOk()->assertJson(['server_url' => 'rtmp://ovsu.mycdn.me/input/', 'stream_key' => self::KEY]);

        // явный выбор другого канала
        $other = $this->addChannel(['label' => 'other', 'server_url' => 'rtmps://o.ru/live', 'stream_key' => 'OTHERKEY-xx'])->json('channel.id');
        $this->select($other)->assertOk();
        $this->start()->assertOk()->assertJson(['server_url' => 'rtmps://o.ru/live', 'stream_key' => 'OTHERKEY-xx']);

        // разовый ключ: добавляем назначение и возвращаемся с канала
        $this->as($this->owner)->postJson("/events/{$this->event->id}/broadcast/courts/{$this->court->id}/destinations", [
            'platform' => 'rutube', 'server_url' => 'rtmps://once.ru/live', 'stream_key' => 'ONCEKEY-xx',
        ])->assertOk();
        $this->select(null)->assertOk();
        $this->assertSame(0, BroadcastDestination::where('court_id', $this->court->id)->whereNotNull('channel_id')->count());
        $this->start()->assertOk()->assertJson(['server_url' => 'rtmps://once.ru/live', 'stream_key' => 'ONCEKEY-xx']);
        $this->assertNotNull($id);
    }

    public function test_deleted_channel_gives_choose_channel_error(): void
    {
        $id = $this->addChannel()->json('channel.id');
        $this->select($id)->assertOk();
        $this->start()->assertOk(); // создаёт ссылку оверлея — площадка появится на экране камеры

        // используется на 1 площадке → без confirm 409
        $r = $this->as($this->owner)->deleteJson("/broadcast/channels/$id")->assertStatus(409);
        $this->assertSame(1, $r->json('used'));
        $this->assertNotNull(BroadcastChannel::find($id));
        $this->as($this->owner)->deleteJson("/broadcast/channels/$id", ['confirm' => 1])->assertOk();
        $this->assertNull(BroadcastChannel::find($id));

        $r = $this->start()->assertStatus(422)->assertJson(['ok' => false, 'code' => 'no_channel']);
        $this->assertSame(__('stream.cam_no_channel'), $r->json('message'));
        $this->assertStringNotContainsString('stream_key', $r->getContent());

        // страницы живы, площадка помечена
        $this->as($this->owner)->get("/events/{$this->event->id}/broadcast")->assertOk();
        $this->assertStringContainsString(__('stream.cam_no_channel'), $this->as($this->owner)->get('/broadcast/camera')->getContent());
    }

    public function test_unused_channel_deletes_without_confirm_and_default_moves(): void
    {
        $a = $this->addChannel()->json('channel.id');
        $b = $this->addChannel(['label' => 'b'])->json('channel.id');
        $this->as($this->owner)->deleteJson("/broadcast/channels/$a")->assertOk();
        $this->assertTrue((bool) BroadcastChannel::find($b)->is_default);
        $this->as($this->owner)->postJson("/broadcast/channels/$b/default")->assertOk();
    }

    public function test_legacy_destination_still_works_and_default_channel_does_not_override_it(): void
    {
        $this->addChannel(); // канал по умолчанию есть
        DB::table('broadcast_destinations')->insert([
            'court_id' => $this->court->id, 'platform' => 'vk', 'server_url' => 'rtmp://legacy.ru/in', 'stream_key' => encrypt(self::LEGACY, false),
            'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->start()->assertOk()->assertJson(['server_url' => 'rtmp://legacy.ru/in', 'stream_key' => self::LEGACY]);
        $destId = DB::table('broadcast_destinations')->where('court_id', $this->court->id)->value('id');
        $this->as($this->staff)->postJson("/events/{$this->event->id}/broadcast/courts/{$this->court->id}/destinations/$destId/reveal")
            ->assertOk()->assertJson(['stream_key' => self::LEGACY]);
    }

    public function test_camera_shows_channel_label_without_secrets(): void
    {
        $id = $this->addChannel()->json('channel.id');
        $this->select($id);
        $this->start()->assertOk();
        $html = $this->as($this->staff)->get('/broadcast/camera')->assertOk()->getContent();
        $this->assertStringContainsString('ZZ_CH', $html);
        $this->assertStringNotContainsString(self::KEY, $html);
    }

    public function test_channels_page_has_no_key(): void
    {
        $this->addChannel();
        $html = $this->as($this->owner)->get('/broadcast/channels')->assertOk()->getContent();
        $this->assertStringContainsString('ZZ_CH', $html);
        $this->assertStringNotContainsString(self::KEY, $html);
    }

    public function test_validation_and_youtube_autofill(): void
    {
        foreach (['http://x.ru', 'x.ru', '', 'rtmp://'] as $bad) {
            $this->addChannel(['server_url' => $bad])->assertStatus(422);
        }
        $r = $this->addChannel(['platform' => 'youtube', 'server_url' => ''])->assertOk();
        $this->assertSame(BroadcastDestination::YOUTUBE_SERVER, BroadcastChannel::find($r->json('channel.id'))->server_url);
        // правка без ключа сохраняет прежний
        $id = $r->json('channel.id');
        $this->as($this->owner)->patchJson("/broadcast/channels/$id", ['platform' => 'youtube', 'server_url' => '', 'label' => 'renamed'])->assertOk();
        $c = BroadcastChannel::find($id);
        $this->assertSame('renamed', $c->label);
        $this->assertSame(self::KEY, $c->stream_key);
    }
}
