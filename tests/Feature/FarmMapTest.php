<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\FarmRole;
use App\Enums\ProductionUnitStatus;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Input;
use App\Models\PhytosanitaryTreatment;
use App\Models\ProductionUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class FarmMapTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_le_plan_affiche_les_parcelles_les_cultures_et_les_alertes(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-MAP',
            'name' => 'Parcelle Nord',
            'area_m2' => 500,
        ]);
        $crop = Crop::factory()->create(['farm_id' => $farm->id, 'name' => 'Tomate']);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'status' => CampaignStatus::Ongoing->value,
        ]);

        Activity::factory()->late()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Désherbage urgent',
        ]);

        Input::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Engrais foliaire',
            'quantity' => 1,
            'minimum_stock' => 10,
        ]);

        $this->actingAsFarmMember($farm);

        $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('P-MAP')
            ->assertSee('Parcelle Nord')
            ->assertSee('Tomate')
            ->assertSee('Désherbage urgent')
            ->assertSee('Engrais foliaire')
            ->assertSee('Vue 3D')
            ->assertSee('Plan schématique');
    }

    public function test_le_plan_ne_montre_que_les_parcelles_de_l_exploitation_active(): void
    {
        $farm = $this->createFarm();
        ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-INTERNE',
            'name' => 'Parcelle interne',
        ]);

        $otherFarm = $this->createFarm();
        ProductionUnit::factory()->create([
            'farm_id' => $otherFarm->id,
            'code' => 'P-EXTERNE',
            'name' => 'Parcelle externe',
        ]);

        $this->actingAsFarmMember($farm);

        $response = $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('P-INTERNE')
            ->assertDontSee('P-EXTERNE');

        $response->assertViewHas('map', function (array $map): bool {
            return count($map['units']) === 1
                && $map['stats']['units'] === 1;
        });
    }

    public function test_une_recolte_bloquee_par_un_traitement_est_signalee_sur_le_plan(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-TRAITE',
        ]);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
        ]);

        PhytosanitaryTreatment::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'applied_at' => now()->subDay(),
            'minimum_harvest_at' => now()->addDays(5),
        ]);

        $this->actingAsFarmMember($farm);

        $response = $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('P-TRAITE')
            ->assertSee('Récolte bloquée jusqu’au');

        $response->assertViewHas('map', function (array $map): bool {
            return $map['stats']['blocked_harvests'] === 1
                && $map['units'][0]['blocked_until'] !== null;
        });
    }

    public function test_le_plan_est_accessible_a_un_agent(): void
    {
        $farm = $this->createFarm();
        ProductionUnit::factory()->create(['farm_id' => $farm->id]);

        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('farm-map.index'))->assertOk();
    }

    public function test_les_dimensions_optionnelles_sont_enregistrees_avec_la_parcelle(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $this->post(route('production-units.store'), [
            'code' => 'P-DIM',
            'name' => 'Parcelle dimensionnée',
            'area_m2' => 120,
            'length_m' => 12,
            'width_m' => 10,
            'location' => 'Rangée 4',
            'status' => ProductionUnitStatus::Available->value,
        ])->assertRedirect(route('production-units.index'));

        $this->assertDatabaseHas('production_units', [
            'farm_id' => $farm->id,
            'code' => 'P-DIM',
            'length_m' => 12,
            'width_m' => 10,
        ]);
    }
}
