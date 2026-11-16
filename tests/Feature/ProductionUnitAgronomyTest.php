<?php

namespace Tests\Feature;

use App\Enums\ProductionUnitStatus;
use App\Models\ProductionUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ProductionUnitAgronomyTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_responsable_cree_une_parcelle_avec_ses_caracteristiques_agronomiques(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $response = $this->post(route('production-units.store'), [
            'code' => 'P-AGRO',
            'name' => 'Parcelle agro',
            'area_m2' => 250,
            'location' => 'Rangée 4',
            'status' => ProductionUnitStatus::Available->value,
            'soil_type' => 'Limon argileux',
            'irrigation_type' => 'Goutte-à-goutte',
            'sun_exposure' => 'Plein soleil',
            'slope' => 'Faible',
            'notes' => 'Sol drainé, prévoir une analyse annuelle.',
        ]);

        $response->assertRedirect(route('production-units.index'));

        $this->assertDatabaseHas('production_units', [
            'farm_id' => $farm->id,
            'code' => 'P-AGRO',
            'soil_type' => 'Limon argileux',
            'irrigation_type' => 'Goutte-à-goutte',
            'sun_exposure' => 'Plein soleil',
            'slope' => 'Faible',
            'notes' => 'Sol drainé, prévoir une analyse annuelle.',
        ]);
    }

    public function test_un_responsable_modifie_les_caracteristiques_agronomiques_d_une_parcelle(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'soil_type' => 'Sableux',
            'irrigation_type' => null,
        ]);

        $response = $this->put(route('production-units.update', $unit), [
            'code' => $unit->code,
            'name' => $unit->name,
            'area_m2' => $unit->area_m2,
            'location' => $unit->location,
            'status' => $unit->status->value,
            'soil_type' => 'Argilo-limoneux',
            'irrigation_type' => 'Aspersion',
            'sun_exposure' => 'Mi-ombre',
            'slope' => 'Modérée',
            'notes' => 'Zone sujette au ruissellement.',
        ]);

        $response->assertRedirect(route('production-units.index'));

        $this->assertDatabaseHas('production_units', [
            'id' => $unit->id,
            'soil_type' => 'Argilo-limoneux',
            'irrigation_type' => 'Aspersion',
            'sun_exposure' => 'Mi-ombre',
            'slope' => 'Modérée',
            'notes' => 'Zone sujette au ruissellement.',
        ]);
    }
}
