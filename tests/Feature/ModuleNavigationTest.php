<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ModuleNavigationTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_le_hub_regroupe_les_fonctions_secondaires(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $this->get(route('modules.index'))
            ->assertOk()
            ->assertSee('Toutes les fonctions')
            ->assertSee('Production')
            ->assertSee('Commercial & finances')
            ->assertSee(route('crops.index'))
            ->assertSee(route('produce-lots.index'))
            ->assertSee(route('sales.index'))
            ->assertSee(route('expenses.index'))
            ->assertSee(route('resources.index'))
            ->assertSee(route('team.index'))
            ->assertSee(route('audit.index'));
    }

    public function test_un_agent_ne_voit_pas_les_fonctions_reservees_a_l_administration(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('modules.index'))
            ->assertOk()
            ->assertSee(route('crops.index'))
            ->assertDontSee(route('team.index'))
            ->assertDontSee(route('audit.index'));
    }

    public function test_le_menu_principal_renvoie_vers_le_plan_et_le_hub_sans_lister_toutes_les_pages(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('farm-map.index').'"', false)
            ->assertSee('href="'.route('modules.index').'"', false)
            ->assertDontSee('href="'.route('crops.index').'"', false);
    }
}
