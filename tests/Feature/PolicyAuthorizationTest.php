<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\FarmRole;
use App\Enums\ProductionUnitStatus;
use App\Enums\StockMovementType;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Harvest;
use App\Models\Input;
use App\Models\ProductionUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_agent_ne_peut_pas_creer_ou_modifier_une_parcelle(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('production-units.create'))->assertForbidden();

        $this->post(route('production-units.store'), [
            'code' => 'P99',
            'name' => 'Parcelle interdite',
            'area_m2' => 100,
            'location' => 'Rangée 99',
            'status' => ProductionUnitStatus::Available->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('production_units', ['code' => 'P99']);

        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);

        $this->get(route('production-units.edit', $unit))->assertForbidden();
    }

    public function test_un_responsable_peut_creer_une_parcelle(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $this->post(route('production-units.store'), [
            'code' => 'P10',
            'name' => 'Parcelle autorisée',
            'area_m2' => 250,
            'location' => 'Rangée 10',
            'status' => ProductionUnitStatus::Available->value,
        ])->assertRedirect(route('production-units.index'));

        $this->assertDatabaseHas('production_units', [
            'farm_id' => $farm->id,
            'code' => 'P10',
        ]);
    }

    public function test_un_agent_ne_peut_pas_modifier_une_culture(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);
        $crop = Crop::factory()->create(['farm_id' => $farm->id]);

        $this->get(route('crops.edit', $crop))->assertForbidden();
        $this->put(route('crops.update', $crop), [
            'name' => 'Culture modifiée',
            'variety' => 'Test',
        ])->assertForbidden();
    }

    public function test_un_agent_peut_enregistrer_une_activite(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);

        $this->post(route('activities.store'), [
            'campaign_id' => $campaign->id,
            'type' => ActivityType::Weeding->value,
            'title' => 'Désherbage manuel',
            'planned_date' => now()->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Désherbage manuel',
        ]);
    }

    public function test_un_agent_ne_peut_pas_ajuster_le_stock_mais_peut_saisir_une_entree(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);
        $input = Input::factory()->seed()->create(['farm_id' => $farm->id, 'quantity' => 10]);

        $this->post(route('stock-movements.store'), [
            'input_id' => $input->id,
            'type' => StockMovementType::Adjustment->value,
            'quantity' => 5,
            'reason' => 'Ajustement interdit',
        ])->assertForbidden();

        $this->post(route('stock-movements.store'), [
            'input_id' => $input->id,
            'type' => StockMovementType::Entry->value,
            'quantity' => 5,
            'reason' => 'Entrée autorisée',
        ])->assertRedirect(route('stock-movements.index'));

        $this->assertDatabaseHas('stock_movements', [
            'input_id' => $input->id,
            'type' => StockMovementType::Entry->value,
        ]);
    }

    public function test_un_agent_ne_peut_pas_supprimer_une_recolte(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $harvest = Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
        ]);

        $this->delete(route('harvests.destroy', $harvest))->assertForbidden();

        $this->assertDatabaseHas('harvests', ['id' => $harvest->id]);
    }

    public function test_le_journal_est_reserve_aux_responsables(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('audit.index'))->assertForbidden();
    }
}
