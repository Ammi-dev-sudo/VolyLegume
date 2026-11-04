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
            ->assertSee('Extrusion 3D')
            ->assertSee('Plan schématique de secours');
    }

    public function test_les_contours_enregistres_alimentent_les_vues_2d_et_3d(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-CONTOUR',
            'boundary' => $this->validBoundary(),
        ]);

        $this->actingAsFarmMember($farm);

        $response = $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('P-CONTOUR')
            ->assertSee('Extrusion 3D')
            ->assertDontSee('Contours à renseigner');

        $response->assertViewHas('map', function (array $map) use ($unit): bool {
            $row = collect($map['units'])->firstWhere('unit.id', $unit->id);
            $geometry = collect($map['geometry']['units'])->firstWhere('id', $unit->id);

            return $row !== null
                && $row['has_boundary'] === true
                && count($row['boundary_points']) === 4
                && $row['boundary_area_m2'] > 2500
                && $geometry !== null
                && $geometry['has_boundary'] === true
                && $geometry['edit_url'] !== null
                && $map['stats']['with_boundary'] === 1
                && $map['stats']['without_boundary'] === 0
                && $map['geometry']['default_mode'] === '2d';
        });
    }

    public function test_un_responsable_peut_enregistrer_un_contour(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'latitude' => null,
            'longitude' => null,
            'area_m2' => 10,
        ]);

        $this->actingAsFarmMember($farm);

        $this->put(route('production-units.boundary.update', $unit), [
            'boundary' => json_encode($this->validBoundary()),
            'update_area' => '1',
        ])
            ->assertRedirect(route('production-units.boundary.edit', $unit))
            ->assertSessionHas('status');

        $unit->refresh();

        $this->assertTrue($unit->hasBoundary());
        $this->assertSame(4, count($unit->boundaryPoints()));
        $this->assertGreaterThan(2500, $unit->boundaryAreaM2());
        $this->assertSame((float) $unit->area_m2, $unit->boundaryAreaM2());
        $this->assertNotNull($unit->latitude);
        $this->assertNotNull($unit->longitude);
    }

    public function test_un_contour_invalide_est_refuse(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'boundary' => null,
        ]);

        $this->actingAsFarmMember($farm);

        $this->from(route('production-units.boundary.edit', $unit))
            ->put(route('production-units.boundary.update', $unit), [
                'boundary' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[[47.5, -18.9], [47.5, -18.9], [47.5, -18.9]]],
                ]),
            ])
            ->assertRedirect(route('production-units.boundary.edit', $unit))
            ->assertSessionHasErrors('boundary');

        $this->assertNull($unit->fresh()->boundary);
    }

    public function test_un_agent_ne_peut_pas_modifier_les_contours(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);

        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('production-units.boundary.edit', $unit))->assertForbidden();
        $this->put(route('production-units.boundary.update', $unit), [
            'boundary' => json_encode($this->validBoundary()),
        ])->assertForbidden();
    }

    public function test_un_contour_ne_peut_pas_etre_modifie_depuis_une_autre_exploitation(): void
    {
        $farm = $this->createFarm();
        $otherFarm = $this->createFarm();
        $foreignUnit = ProductionUnit::factory()->create(['farm_id' => $otherFarm->id]);

        $this->actingAsFarmMember($farm);

        $this->get(route('production-units.boundary.edit', $foreignUnit))->assertNotFound();
        $this->put(route('production-units.boundary.update', $foreignUnit), [
            'boundary' => json_encode($this->validBoundary()),
        ])->assertNotFound();
    }

    public function test_un_contour_peut_etre_supprime(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'boundary' => $this->validBoundary(),
        ]);

        $this->actingAsFarmMember($farm);

        $this->put(route('production-units.boundary.update', $unit), [
            'remove_boundary' => '1',
        ])
            ->assertRedirect(route('production-units.boundary.edit', $unit))
            ->assertSessionHas('status');

        $this->assertNull($unit->fresh()->boundary);
    }

    /**
     * @return array{type: string, coordinates: array<int, array<int, array{0: float, 1: float}>>}
     */
    private function validBoundary(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [47.5000, -18.9000],
                [47.5005, -18.9000],
                [47.5005, -18.9005],
                [47.5000, -18.9005],
                [47.5000, -18.9000],
            ]],
        ];
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
