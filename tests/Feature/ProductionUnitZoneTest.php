<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\FarmRole;
use App\Enums\ReferenceType;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\ProductionUnit;
use App\Models\ProductionUnitZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ProductionUnitZoneTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_responsable_cree_une_zone_dans_la_limite_de_la_parcelle(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => 100]);
        $this->actingAsFarmMember($farm);

        $this->post(route('production-units.zones.store', $unit), [
            'code' => 'Z1',
            'name' => 'Zone nord',
            'area_m2' => 40,
            'irrigation_type' => 'Goutte-à-goutte',
            'is_active' => 1,
        ])->assertRedirect();

        $zone = ProductionUnitZone::firstOrFail();
        $this->assertSame($farm->id, $zone->farm_id);
        $this->assertSame($unit->id, $zone->production_unit_id);
        $this->assertTrue($zone->is_active);

        $this->get(route('production-units.show', $unit))
            ->assertOk()
            ->assertSee('Zones de culture')
            ->assertSee('Zone nord');
    }

    public function test_une_zone_peut_etre_modifiee_et_desactivee(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => 100]);
        $zone = ProductionUnitZone::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'area_m2' => 30,
            'name' => 'Ancienne zone',
            'is_active' => true,
        ]);
        $this->actingAsFarmMember($farm);

        $this->put(route('zones.update', $zone), [
            'code' => $zone->code,
            'name' => 'Zone rénovée',
            'area_m2' => 45,
            'is_active' => 1,
        ])->assertRedirect();

        $zone->refresh();
        $this->assertSame('Zone rénovée', $zone->name);
        $this->assertSame('45.00', $zone->area_m2);
        $this->assertTrue($zone->is_active);

        $this->put(route('zones.update', $zone), [
            'code' => $zone->code,
            'name' => 'Zone rénovée',
            'area_m2' => 45,
        ])->assertRedirect();

        $this->assertFalse($zone->refresh()->is_active);
    }

    public function test_la_somme_des_zones_ne_peut_pas_depasser_la_superficie(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => 100]);
        ProductionUnitZone::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'area_m2' => 70,
        ]);
        $this->actingAsFarmMember($farm);

        $this->post(route('production-units.zones.store', $unit), [
            'code' => 'Z2',
            'name' => 'Zone sud',
            'area_m2' => 50,
            'is_active' => 1,
        ])->assertSessionHasErrors('area_m2');

        $this->assertSame(1, ProductionUnitZone::count());
    }

    public function test_un_agent_ne_peut_pas_gerer_les_zones(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $zone = ProductionUnitZone::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
        ]);
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->post(route('production-units.zones.store', $unit), [
            'code' => 'Z3',
            'name' => 'Zone est',
            'area_m2' => 10,
        ])->assertForbidden();

        $this->delete(route('zones.destroy', $zone))->assertForbidden();
    }

    public function test_une_campagne_ne_peut_pas_etre_rattachee_a_une_zone_d_une_autre_parcelle(): void
    {
        $farm = $this->createFarm();
        $unitA = ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => 100]);
        $unitB = ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => 100]);
        $zoneB = ProductionUnitZone::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unitB->id,
            'area_m2' => 30,
        ]);
        $this->actingAsFarmMember($farm);

        $this->post(route('campaigns.store'), [
            'production_unit_id' => $unitA->id,
            'production_unit_zone_id' => $zoneB->id,
            'crop_id' => Crop::factory()->create(['farm_id' => $farm->id])->id,
            'name' => 'Campagne zone invalide',
            'start_date' => now()->toDateString(),
            'reference_type' => ReferenceType::Sowing->value,
            'expected_end_date' => now()->addMonth()->toDateString(),
            'cultivated_area_m2' => 20,
            'status' => CampaignStatus::Planned->value,
        ])->assertSessionHasErrors('production_unit_zone_id');

        $this->assertSame(0, Campaign::count());
    }
}
