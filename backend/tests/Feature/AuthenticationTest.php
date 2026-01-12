<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login', absolute: false));
        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->from(route('login', absolute: false))
            ->withSession(['_token' => csrf_token()])
            ->post(route('login.store', absolute: false), [
                '_token' => csrf_token(),
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(); // не привязываемся к dashboard
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->from(route('login', absolute: false))
            ->withSession(['_token' => csrf_token()])
            ->post(route('login.store', absolute: false), [
                '_token' => csrf_token(),
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }
}
