<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Harvest;
use App\Models\ProductionUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ProductionUnitShowTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_membre_consulte_la_fiche_detaille_d_une_parcelle(): void
    {
        $farm = $this->createFarm();
        $user = $this->actingAsFarmMember($farm, FarmRole::Agent);

        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Parcelle Tomate',
            'code' => 'PT-01',
        ]);

        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
        ]);

        $activity = Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Désherbage de la parcelle',
            'created_by' => $user->id,
        ]);

        $harvest = Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'harvest_code' => 'REC-001',
            'recorded_by' => $user->id,
        ]);

        $this->get(route('production-units.show', $unit))
            ->assertOk()
            ->assertSee('Parcelle Tomate')
            ->assertSee('PT-01')
            ->assertSee($campaign->name)
            ->assertSee($campaign->crop->name)
            ->assertSee($activity->title)
            ->assertSee('REC-001')
            ->assertSee('Campagnes');
    }

    public function test_une_parcelle_d_une_autre_exploitation_est_introuvable(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $otherUnit = ProductionUnit::factory()->create();

        $this->get(route('production-units.show', $otherUnit))->assertNotFound();
    }

    public function test_un_agent_peut_consulter_la_fiche_sans_pouvoir_la_modifier(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);

        $this->get(route('production-units.show', $unit))->assertOk();
        $this->get(route('production-units.edit', $unit))->assertForbidden();
    }
}
