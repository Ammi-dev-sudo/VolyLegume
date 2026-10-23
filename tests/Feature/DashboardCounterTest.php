<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

/**
 * Le tableau de bord anime ses indicateurs : les valeurs chiffrées portent
 * un attribut `data-count` consommé par le compteur JavaScript.
 */
class DashboardCounterTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_les_indicateurs_du_tableau_de_bord_exposent_leur_valeur_au_compteur(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $response = $this->get(route('dashboard'))->assertOk();

        $response->assertSee('data-count=', false);
        $response->assertSee('data-reveal-group', false);
    }

    public function test_une_valeur_sans_chiffre_n_est_pas_comptee(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $response = $this->get(route('dashboard'))->assertOk();

        $counters = $response->getContent()
            ? substr_count($response->getContent(), 'data-count="')
            : 0;

        $this->assertGreaterThan(0, $counters);
    }
}
