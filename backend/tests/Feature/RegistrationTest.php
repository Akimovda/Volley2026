<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get(route('register', absolute: false));
        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get(route('register', absolute: false));
        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this
            ->withSession(['_token' => csrf_token()])
            ->post(route('register.store', absolute: false), [
                '_token' => csrf_token(),
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
            ]);

        $this->assertAuthenticated();

        // у вас может не быть dashboard — не привязываемся жестко
        if (Route::has('dashboard')) {
            $response->assertRedirect(route('dashboard', absolute: false));
        } else {
            $response->assertRedirect();
        }
    }
}
