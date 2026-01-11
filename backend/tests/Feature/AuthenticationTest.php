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
        // Важно: пароль у factory по умолчанию должен быть bcrypt('password') (Jetstream/Fortify так делает).
        $user = User::factory()->create();

        // В вашем проекте после логина редирект НЕ обязан быть на /dashboard (он под verified),
        // поэтому проверяем, что авторизация произошла, и что ответ — редирект куда угодно.
        $response = $this
            ->from(route('login', absolute: false))
            ->post(route('login.store', absolute: false), [
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
            ->post(route('login.store', absolute: false), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }
}
