<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_peut_se_connecter(): void
    {
        $user = User::factory()->create([
            'email' => 'rakoto@example.mg',
            'password' => Hash::make('motdepasse'),
            'is_active' => true,
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => 'rakoto@example.mg',
            'password' => 'motdepasse',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        User::factory()->create([
            'email' => 'inactif@example.mg',
            'password' => Hash::make('motdepasse'),
            'is_active' => false,
        ]);

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'inactif@example.mg',
            'password' => 'motdepasse',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_l_inscription_cree_un_compte_actif(): void
    {
        $response = $this->post(route('register.attempt'), [
            'name' => 'Rasoa',
            'email' => 'rasoa@example.mg',
            'password' => 'motdepasse1',
            'password_confirmation' => 'motdepasse1',
        ]);

        $response->assertRedirect(route('farms.create'));

        $this->assertDatabaseHas('users', [
            'email' => 'rasoa@example.mg',
            'is_active' => true,
        ]);
    }
}
