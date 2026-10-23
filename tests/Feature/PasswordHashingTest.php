<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Les mots de passe ne sont jamais stockés en clair : ils sont hachés avec
 * argon2id. Les hachages bcrypt historiques restent acceptés et sont
 * réécrits en argon2id dès la première connexion réussie.
 */
class PasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_mot_de_passe_est_stocke_hache_et_jamais_en_clair(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
    }

    public function test_un_hachage_bcrypt_legacy_est_accepte_puis_reecrit_en_argon2id(): void
    {
        $user = User::factory()->create();

        // Écriture directe : le cast « hashed » refuse d'enregistrer un
        // hachage dont l'algorithme diffère du driver configuré.
        User::whereKey($user->id)->update([
            'password' => password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        $user->refresh();

        $this->assertSame('bcrypt', password_get_info($user->password)['algoName']);

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('argon2id', password_get_info($user->fresh()->password)['algoName']);
    }

    public function test_un_mot_de_passe_incorrect_ne_reconnecte_pas(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
