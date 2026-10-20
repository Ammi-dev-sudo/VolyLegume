<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Models\ProductionUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class FarmIsolationTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_membre_ne_peut_pas_acceder_aux_ressources_d_une_autre_exploitation(): void
    {
        $farmA = $this->createFarm();
        $farmB = $this->createFarm();
        $unitB = ProductionUnit::factory()->create(['farm_id' => $farmB->id]);

        $this->actingAsFarmMember($farmA);

        $this->get(route('production-units.edit', $unitB))->assertNotFound();
    }

    public function test_un_membre_voit_les_ressources_de_son_exploitation(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);

        $this->actingAsFarmMember($farm);

        $this->get(route('production-units.edit', $unit))->assertOk();
    }

    public function test_le_basculement_vers_une_exploitation_non_rattachee_est_refuse(): void
    {
        $farmA = $this->createFarm();
        $farmB = $this->createFarm();

        $this->actingAsFarmMember($farmA);

        $this->post(route('farms.switch', $farmB))->assertNotFound();
    }

    public function test_l_exploitation_active_est_la_premiere_du_membre_sans_session(): void
    {
        $farm = $this->createFarm();
        $other = $this->createFarm();

        $user = $this->memberOf($farm);
        $this->memberOf($other, FarmRole::Agent);

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk()->assertSessionHas('active_farm_id', $farm->id);
    }

    public function test_un_utilisateur_sans_exploitation_est_redirige_a_la_creation(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user);

        $this->get(route('production-units.index'))->assertRedirect(route('farms.create'));
    }
}
