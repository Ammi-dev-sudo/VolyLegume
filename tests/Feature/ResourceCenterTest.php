<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ResourceCenterTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_le_centre_de_ressources_est_accessible_aux_membres_connectes(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Admin);

        $this->get(route('resources.index'))
            ->assertOk()
            ->assertSee('Tutoriels vidéo')
            ->assertSee('Rôles &amp; permissions', false)
            ->assertSee('Votre rôle')
            ->assertSee('images/logo.png', false);
    }

    public function test_un_visiteur_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('resources.index'))->assertRedirect(route('login'));
    }
}
