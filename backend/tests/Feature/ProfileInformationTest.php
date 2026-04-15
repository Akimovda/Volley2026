<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_profile_information_is_available(): void
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        $this->assertEquals($user->name, $component->state['name']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create([
            'first_name' => null,
            'last_name'  => null,
        ]));

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', ['name' => 'Test Name', 'email' => 'test@example.com'])
            ->call('updateProfileInformation');

        $fresh = $user->fresh();
        // Debug: dump what is in DB
        $rawName = $fresh->getRawOriginal('name');
        $firstName = $fresh->first_name;
        $lastName = $fresh->last_name;
        $this->assertEquals('test@example.com', $fresh->email,
            "Email mismatch");
        $this->assertEquals('Test Name', $rawName,
            "Raw name mismatch. first_name={$firstName}, last_name={$lastName}");
    }
}
