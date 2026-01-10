<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSimpleLinkTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_links_telegram_to_current_user()
    {
        $u = User::factory()->create([
            'telegram_id' => null,
            'vk_id' => 'vk_1',
        ]);

        // Здесь callback сделан заглушкой через query/input — подстройте под ваш реальный callback.
        $resp = $this->actingAs($u)
            ->withSession(['auth_provider' => 'vk'])
            ->get('/account/link/telegram/callback?tg_id=tg_123&tg_username=alice');

        $resp->assertRedirect('/user/profile');

        $u->refresh();
        $this->assertEquals('tg_123', (string) $u->telegram_id);
        $this->assertEquals('alice', (string) $u->telegram_username);
    }

    /** @test */
    public function it_rejects_linking_telegram_if_it_is_used_by_another_user()
    {
        User::factory()->create(['telegram_id' => 'tg_999']);
        $u = User::factory()->create(['telegram_id' => null]);

        $resp = $this->actingAs($u)
            ->get('/account/link/telegram/callback?tg_id=tg_999&tg_username=bob');

        $resp->assertRedirect('/user/profile');
        $u->refresh();
        $this->assertNull($u->telegram_id);
    }
}
