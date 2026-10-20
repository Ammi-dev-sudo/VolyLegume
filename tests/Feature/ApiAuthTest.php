<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_des_identifiants_valides_renvoient_un_token(): void
    {
        $user = User::factory()->create([
            'email' => 'api@volylegume.mg',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'api@volylegume.mg',
            'password' => 'secret',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'api@volylegume.mg');
    }

    public function test_des_identifiants_invalides_repondent_422(): void
    {
        User::factory()->create([
            'email' => 'api@volylegume.mg',
            'password' => Hash::make('secret'),
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'api@volylegume.mg',
            'password' => 'mauvais',
        ])->assertUnprocessable();

        $this->postJson('/api/login', [
            'email' => 'inconnu@volylegume.mg',
            'password' => 'secret',
        ])->assertUnprocessable();
    }

    public function test_un_compte_inactif_ne_peut_pas_se_connecter(): void
    {
        User::factory()->create([
            'email' => 'api@volylegume.mg',
            'password' => Hash::make('secret'),
            'is_active' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => 'api@volylegume.mg',
            'password' => 'secret',
        ])->assertUnprocessable();
    }

    public function test_le_token_peut_etre_revoque_avec_logout(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('api');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/campaigns')
            ->assertUnauthorized();
    }
}
