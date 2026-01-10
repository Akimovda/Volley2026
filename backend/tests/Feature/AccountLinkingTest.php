<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountLinkingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_rejects_linking_to_self_with_same_account_code()
    {
        $u = User::factory()->create([
            'vk_id' => 'vk_1',
            'telegram_id' => null,
        ]);

        // создаём код "в этом же аккаунте"
        DB::table('account_link_codes')->insert([
            'user_id' => $u->id,
            'code_hash' => hash('sha256', 'ABCD'),
            'target_provider' => 'telegram',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($u)
            ->withSession(['auth_provider' => 'vk'])
            ->post('/account/link', ['code' => 'ABCD']);

        $resp->assertSessionHasErrors('code');
    }

    /** @test */
    public function it_links_provider_ids_from_current_user_to_owner_user()
    {
        // owner = аккаунт, который "владеет кодом" (куда привязываем)
        $owner = User::factory()->create([
            'vk_id' => 'vk_owner',
            'telegram_id' => null,
            'telegram_username' => null,
        ]);

        // current = второй аккаунт, который залогинен вторым способом и вводит код
        $tgId = 'tg_' . fake()->unique()->numerify('########');

        $current = User::factory()->create([
            'vk_id' => null,
            'telegram_id' => $tgId,
            'telegram_username' => 'tgname',
        ]);

        // link-code создан owner-ом, target_provider = telegram
        DB::table('account_link_codes')->insert([
            'user_id' => $owner->id,
            'code_hash' => hash('sha256', 'WXYZ'),
            'target_provider' => 'telegram',
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ACT: current вводит код
        $resp = $this->actingAs($current)
            ->withSession(['auth_provider' => 'telegram'])
            ->post('/account/link', ['code' => 'WXYZ']);

        $resp->assertRedirect('/user/profile');

        // ASSERT: telegram перенесён в owner
        $owner->refresh();
        $this->assertEquals($tgId, (string) $owner->telegram_id);
        $this->assertEquals('tgname', (string) $owner->telegram_username);

        // ASSERT: у current очищено (иначе останется второй юзер с тем же telegram_id -> UNIQUE)
        $current->refresh();
        $this->assertNull($current->telegram_id);
        $this->assertNull($current->telegram_username);

        // (опционально) audit
        // $this->assertDatabaseHas('account_link_audits', [
        //     'user_id' => $owner->id,
        //     'linked_from_user_id' => $current->id,
        //     'method' => 'link_code',
        // ]);
    }
}
