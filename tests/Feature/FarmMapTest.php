<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\CampaignStatus;
use App\Enums\FarmRole;
use App\Enums\ProductionUnitStatus;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Harvest;
use App\Models\Input;
use App\Models\IrrigationEvent;
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
            ->assertSee('data-map-basemap="satellite"', false)
            ->assertSee('data-map-basemap="plan"', false)
            ->assertSee('Imagerie Esri plaquée à plat')
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
                && $geometry['points'] === $row['boundary_points']
                && $geometry['edit_url'] !== null
                && $map['stats']['with_boundary'] === 1
                && $map['stats']['without_boundary'] === 0
                && $map['geometry']['default_mode'] === '2d'
                && $map['geometry']['terrain']['has_elevation'] === false
                && $map['geometry']['terrain']['source'] === null;
        });
    }

    public function test_la_vue_3d_signale_l_absence_de_relief_reel(): void
    {
        $farm = $this->createFarm();
        ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'boundary' => $this->validBoundary(),
        ]);

        $this->actingAsFarmMember($farm);

        $response = $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('Relief non disponible')
            ->assertSee('Aucune source d’altitude fiable');

        $response->assertViewHas('map', function (array $map): bool {
            $terrain = $map['geometry']['terrain'];

            return $terrain['has_elevation'] === false
                && $terrain['source'] === null
                && $terrain['label'] === 'Extrusion des contours';
        });
    }

    public function test_une_parcelle_sans_contour_n_a_pas_de_position_inventee(): void
    {
        $farm = $this->createFarm();
        ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-SANS-CONTOUR',
            'latitude' => null,
            'longitude' => null,
            'boundary' => null,
        ]);

        $this->actingAsFarmMember($farm);

        $response = $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('P-SANS-CONTOUR')
            ->assertSee('Contour à dessiner')
            ->assertSee('sans coordonnées');

        $response->assertViewHas('map', function (array $map): bool {
            $row = collect($map['units'])->firstWhere('unit.code', 'P-SANS-CONTOUR');
            $geometry = collect($map['geometry']['units'])->firstWhere('code', 'P-SANS-CONTOUR');

            return $row !== null
                && $row['has_boundary'] === false
                && $row['boundary_points'] === []
                && $row['centroid'] === null
                && $row['map_point'] === null
                && $geometry !== null
                && $geometry['has_boundary'] === false
                && $geometry['points'] === []
                && $geometry['centroid'] === null
                && $geometry['point'] === null
                && $map['stats']['without_boundary'] === 1
                && $map['stats']['without_coordinates'] === 1
                && $map['geometry']['default_mode'] === 'plan';
        });
    }

    public function test_un_contour_enregistre_reste_identique_apres_rechargement(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'boundary' => null,
        ]);

        $this->actingAsFarmMember($farm);

        $this->put(route('production-units.boundary.update', $unit), [
            'boundary' => json_encode($this->validBoundary()),
        ])->assertRedirect(route('production-units.boundary.edit', $unit));

        $reloaded = $unit->fresh();
        $this->assertNotNull($reloaded);

        $points = $reloaded->boundaryPoints();

        $this->assertCount(4, $points);
        $this->assertEquals($this->validBoundary()['coordinates'][0][0], $points[0]);

        $response = $this->get(route('farm-map.index'))->assertOk();

        $response->assertViewHas('map', function (array $map) use ($unit, $points): bool {
            $geometry = collect($map['geometry']['units'])->firstWhere('id', $unit->id);

            return $geometry !== null
                && $geometry['has_boundary'] === true
                && $geometry['points'] === $points
                && $geometry['boundary']['coordinates'][0][0] === $points[0];
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

    public function test_une_parcelle_sans_contour_avec_coordonnees_reste_a_sa_position_connue(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-POINT',
            'latitude' => -18.9123456,
            'longitude' => 47.5123456,
            'boundary' => null,
        ]);

        $this->actingAsFarmMember($farm);

        $response = $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('P-POINT')
            ->assertSee('sans contour enregistré');

        $response->assertViewHas('map', function (array $map) use ($unit): bool {
            $row = collect($map['units'])->firstWhere('unit.id', $unit->id);
            $geometry = collect($map['geometry']['units'])->firstWhere('id', $unit->id);

            return $row !== null
                && $row['has_boundary'] === false
                && $row['boundary_points'] === []
                && $row['centroid'] === null
                && abs($row['map_point']['lat'] - (-18.9123456)) < 1e-9
                && abs($row['map_point']['lng'] - 47.5123456) < 1e-9
                && $geometry !== null
                && $geometry['has_boundary'] === false
                && $geometry['point'] === $row['map_point']
                && $map['stats']['without_boundary'] === 1
                && $map['stats']['without_coordinates'] === 0;
        });
    }

    public function test_la_modification_de_la_fiche_conserve_le_contour(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'boundary' => $this->validBoundary(),
        ]);

        $this->actingAsFarmMember($farm);

        $this->put(route('production-units.update', $unit), [
            'code' => $unit->code,
            'name' => 'Parcelle rénovée',
            'area_m2' => 500,
            'location' => 'Rangée 9',
            'status' => ProductionUnitStatus::Available->value,
        ])->assertRedirect(route('production-units.index'));

        $reloaded = $unit->fresh();
        $this->assertNotNull($reloaded);
        $this->assertSame('Parcelle rénovée', $reloaded->name);
        $this->assertCount(4, $reloaded->boundaryPoints());
        $this->assertSame($this->validBoundary()['coordinates'][0][0], $reloaded->boundaryPoints()[0]);
    }

    public function test_supprimer_le_contour_conserve_les_autres_donnees(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-CONSERVEE',
            'name' => 'Parcelle conservée',
            'area_m2' => 321,
            'latitude' => -18.9,
            'longitude' => 47.5,
            'boundary' => $this->validBoundary(),
        ]);

        $this->actingAsFarmMember($farm);

        $this->put(route('production-units.boundary.update', $unit), [
            'remove_boundary' => '1',
        ])->assertRedirect(route('production-units.boundary.edit', $unit));

        $reloaded = $unit->fresh();
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->boundary);
        $this->assertSame('Parcelle conservée', $reloaded->name);
        $this->assertSame('P-CONSERVEE', $reloaded->code);
        $this->assertSame(321.0, (float) $reloaded->area_m2);
        $this->assertSame(-18.9, (float) $reloaded->latitude);
        $this->assertSame(47.5, (float) $reloaded->longitude);
    }

    public function test_les_couches_exposent_les_donnees_enregistrees_et_leurs_legendes(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-COUCHES',
            'name' => 'Parcelle couches',
            'boundary' => $this->validBoundary(),
        ]);
        $crop = Crop::factory()->create(['farm_id' => $farm->id, 'name' => 'Tomate cerise']);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'status' => CampaignStatus::Ongoing->value,
            'water_quota_m3' => 10,
            'water_alert_threshold_percent' => 80,
        ]);

        Activity::factory()->late()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Alerte de couche',
        ]);

        Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Semis à venir',
            'planned_date' => now()->addDays(3)->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ]);

        Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'harvested_at' => now()->subDay(),
            'quantity' => 12.5,
            'unit' => 'kg',
        ]);

        IrrigationEvent::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'production_unit_id' => $unit->id,
            'volume_m3' => 8.5,
            'irrigated_at' => now()->toDateString(),
        ]);

        $this->actingAsFarmMember($farm);

        $response = $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('Couches d’information')
            ->assertSee('Cultures en cours')
            ->assertSee('Légende · Cultures en cours')
            ->assertSee('Activités à venir')
            ->assertSee('Activités en retard')
            ->assertSee('Campagnes')
            ->assertSee('Récoltes')
            ->assertSee('Irrigation')
            ->assertSee('État des parcelles')
            ->assertSee('État de récolte')
            ->assertSee('Suivi des récoltes')
            ->assertSee('Tomate cerise');

        $response->assertViewHas('map', function (array $map) use ($unit, $campaign): bool {
            $layers = collect($map['layers'])->keyBy('key');
            $geometry = collect($map['geometry']['units'])->firstWhere('id', $unit->id);
            $irrigation = $geometry['layers']['irrigation'];
            $campaigns = $geometry['layers']['campaigns'];
            $upcoming = $geometry['layers']['upcoming_activities'];
            $late = $geometry['layers']['late_activities'];
            $harvests = $geometry['layers']['harvests'];
            $harvestState = $geometry['layers']['harvest_state'];

            return $layers['crops']['available'] === true
                && $layers['crops']['count'] === 1
                && $layers['crops']['default'] === true
                && collect($layers['crops']['legend'])->contains(fn (array $entry): bool => $entry['label'] === 'Tomate cerise')
                && $layers['campaigns']['available'] === true
                && $layers['campaigns']['count'] === 1
                && $layers['campaigns']['default'] === false
                && collect($layers['campaigns']['legend'])->contains(fn (array $entry): bool => $entry['label'] === 'En cours')
                && $layers['upcoming_activities']['available'] === true
                && $layers['upcoming_activities']['count'] === 1
                && $layers['late_activities']['available'] === true
                && $layers['late_activities']['count'] === 1
                && $layers['harvests']['available'] === true
                && $layers['harvests']['count'] === 1
                && $layers['alerts']['available'] === true
                && $layers['alerts']['count'] === 1
                && $layers['irrigation']['available'] === true
                && $layers['irrigation']['count'] === 1
                && $layers['status']['available'] === true
                && $layers['status']['default'] === false
                && $layers['harvest_state']['available'] === true
                && $layers['harvest_state']['default'] === true
                && $layers['harvest_state']['count'] === 1
                && collect($layers['harvest_state']['legend'])->contains(fn (array $entry): bool => $entry['label'] === 'Récolte en cours')
                && $geometry['layers']['crops']['primary_label'] === 'Tomate cerise'
                && $campaigns['count'] === 1
                && $campaigns['labels'] === [$campaign->name]
                && $upcoming['count'] === 1
                && $upcoming['labels'] === ['Semis à venir']
                && $late['count'] === 1
                && $late['labels'] === ['Alerte de couche']
                && $harvests['count'] === 1
                && str_contains($harvests['labels'][0], '12,5 kg')
                && $harvestState['key'] === 'harvesting'
                && $harvestState['available'] === true
                && $harvestState['harvest_count'] === 1
                && $map['harvest']['recorded_count'] === 1
                && collect($map['harvest']['timeline'])->contains(fn (array $event): bool => $event['kind'] === 'recorded' && $event['unit_code'] === 'P-COUCHES')
                && $geometry['layers']['alerts']['count'] === 1
                && abs($irrigation['used_m3'] - 8.5) < 1e-9
                && abs($irrigation['quota_m3'] - 10) < 1e-9
                && abs($irrigation['usage_percent'] - 85) < 1e-9
                && $irrigation['status'] === 'approaching'
                && $geometry['layers']['status']['value'] === ProductionUnitStatus::Available->value;
        });
    }

    public function test_le_plan_propose_une_synthese_imprimable_sans_inventer_de_donnees(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-IMPRESSION',
            'name' => 'Parcelle imprimable',
            'boundary' => $this->validBoundary(),
        ]);
        $crop = Crop::factory()->create(['farm_id' => $farm->id, 'name' => 'Carotte']);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'status' => CampaignStatus::Ongoing->value,
        ]);
        Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'harvested_at' => now()->subDay(),
            'quantity' => 4.5,
            'unit' => 'kg',
        ]);

        $this->actingAsFarmMember($farm);

        $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('data-map-print', false)
            ->assertSee('data-has-boundaries="true"', false)
            ->assertSee('Imprimer / PDF')
            ->assertSee('Synthèse imprimable du plan')
            ->assertSee('Document généré le')
            ->assertSee('Couches affichées')
            ->assertSee('Carotte')
            ->assertSee('La vue 3D n’est pas imprimée')
            ->assertSee('Aucun relief, aucune position et aucune quantité ne sont inventés');
    }

    public function test_les_recoltes_des_campagnes_terminees_restent_visibles_et_qualifient_la_parcelle(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-TERMINEE',
            'boundary' => $this->validBoundary(),
        ]);
        $crop = Crop::factory()->create(['farm_id' => $farm->id, 'name' => 'Pomme de terre']);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'status' => CampaignStatus::Completed->value,
            'actual_end_date' => now()->subDays(3)->toDateString(),
        ]);
        Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'harvested_at' => now()->subDays(4),
            'quantity' => 8.5,
            'unit' => 'kg',
        ]);

        $this->actingAsFarmMember($farm);

        $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('Pomme de terre')
            ->assertSee('Récolte terminée')
            ->assertViewHas('map', function (array $map) use ($unit, $campaign): bool {
                $layers = collect($map['layers'])->keyBy('key');
                $geometry = collect($map['geometry']['units'])->firstWhere('id', $unit->id);

                return $layers['harvests']['available'] === true
                    && $layers['harvests']['count'] === 1
                    && $layers['harvest_state']['available'] === true
                    && $geometry['harvest_state']['key'] === 'completed'
                    && $geometry['harvest_state']['harvest_count'] === 1
                    && $geometry['harvest_state']['campaign']['id'] === $campaign->id
                    && $map['harvest']['recorded_count'] === 1
                    && collect($map['harvest']['timeline'])->contains(
                        fn (array $event): bool => $event['kind'] === 'recorded'
                            && $event['state_key'] === 'completed'
                            && $event['unit_code'] === 'P-TERMINEE',
                    );
            });
    }

    public function test_les_couches_sans_donnees_sont_desactivees_avec_une_explication(): void
    {
        $farm = $this->createFarm();
        ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-VIDE',
            'boundary' => $this->validBoundary(),
        ]);

        $this->actingAsFarmMember($farm);

        $response = $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('Aucune campagne planifiée, en cours ou suspendue pour ces parcelles.')
            ->assertSee('Aucune activité planifiée à venir pour les campagnes affichées.')
            ->assertSee('Aucune activité en retard pour les campagnes affichées.')
            ->assertSee('Aucune récolte enregistrée pour ces parcelles.')
            ->assertSee('Aucune alerte de parcelle enregistrée.')
            ->assertSee('Aucun quota ni arrosage enregistré pour les campagnes affichées.');

        $response->assertViewHas('map', function (array $map): bool {
            $layers = collect($map['layers'])->keyBy('key');
            $geometry = $map['geometry']['units'][0];

            return $layers['crops']['available'] === false
                && $layers['campaigns']['available'] === false
                && $layers['upcoming_activities']['available'] === false
                && $layers['late_activities']['available'] === false
                && $layers['harvests']['available'] === false
                && $layers['alerts']['available'] === false
                && $layers['irrigation']['available'] === false
                && $layers['status']['available'] === true
                && $geometry['layers']['crops']['available'] === false
                && $geometry['layers']['campaigns']['available'] === false
                && $geometry['layers']['upcoming_activities']['available'] === false
                && $geometry['layers']['late_activities']['available'] === false
                && $geometry['layers']['harvests']['available'] === false
                && $geometry['layers']['alerts']['available'] === false
                && $geometry['layers']['irrigation']['available'] === false
                && $geometry['layers']['irrigation']['events_count'] === 0
                && $geometry['layers']['irrigation']['quota_m3'] === null;
        });
    }

    public function test_les_couches_ne_melangent_pas_les_donnees_de_deux_exploitations(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-LOCALE',
            'boundary' => $this->validBoundary(),
        ]);
        $crop = Crop::factory()->create(['farm_id' => $farm->id, 'name' => 'Culture locale']);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'name' => 'Campagne locale',
            'water_quota_m3' => 20,
        ]);
        Activity::factory()->late()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Alerte locale',
        ]);
        Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Activité à venir locale',
            'planned_date' => now()->addDays(2)->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ]);
        Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'harvested_at' => now()->subDay(),
            'quantity' => 3,
            'unit' => 'kg',
        ]);
        IrrigationEvent::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'production_unit_id' => $unit->id,
            'volume_m3' => 4,
        ]);

        $otherFarm = $this->createFarm();
        $foreignUnit = ProductionUnit::factory()->create([
            'farm_id' => $otherFarm->id,
            'code' => 'P-ETRANGERE',
            'boundary' => $this->validBoundary(),
        ]);
        $foreignCrop = Crop::factory()->create(['farm_id' => $otherFarm->id, 'name' => 'Culture étrangère']);
        $foreignCampaign = Campaign::factory()->create([
            'farm_id' => $otherFarm->id,
            'production_unit_id' => $foreignUnit->id,
            'crop_id' => $foreignCrop->id,
            'name' => 'Campagne étrangère',
            'water_quota_m3' => 999,
        ]);
        Activity::factory()->late()->create([
            'farm_id' => $otherFarm->id,
            'campaign_id' => $foreignCampaign->id,
            'title' => 'Alerte étrangère',
        ]);
        Activity::factory()->create([
            'farm_id' => $otherFarm->id,
            'campaign_id' => $foreignCampaign->id,
            'title' => 'Activité à venir étrangère',
            'planned_date' => now()->addDays(2)->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ]);
        Harvest::factory()->create([
            'farm_id' => $otherFarm->id,
            'campaign_id' => $foreignCampaign->id,
            'harvested_at' => now()->subDay(),
            'quantity' => 900,
            'unit' => 'kg',
        ]);
        IrrigationEvent::factory()->create([
            'farm_id' => $otherFarm->id,
            'campaign_id' => $foreignCampaign->id,
            'production_unit_id' => $foreignUnit->id,
            'volume_m3' => 900,
        ]);

        $this->actingAsFarmMember($farm);

        $response = $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('Culture locale')
            ->assertSee('Alerte locale')
            ->assertDontSee('Culture étrangère')
            ->assertDontSee('Campagne étrangère')
            ->assertDontSee('Alerte étrangère')
            ->assertDontSee('Activité à venir étrangère')
            ->assertDontSee('P-ETRANGERE');

        $response->assertViewHas('map', function (array $map) use ($unit): bool {
            $layers = collect($map['layers'])->keyBy('key');
            $geometry = collect($map['geometry']['units'])->firstWhere('id', $unit->id);
            $irrigation = $geometry['layers']['irrigation'];

            return count($map['units']) === 1
                && $layers['crops']['count'] === 1
                && $layers['campaigns']['count'] === 1
                && $layers['upcoming_activities']['count'] === 1
                && $layers['late_activities']['count'] === 1
                && $layers['harvests']['count'] === 1
                && $layers['alerts']['count'] === 1
                && $layers['irrigation']['count'] === 1
                && $geometry['layers']['crops']['labels'] === ['Culture locale']
                && $geometry['layers']['campaigns']['labels'] === ['Campagne locale']
                && $geometry['layers']['upcoming_activities']['labels'] === ['Activité à venir locale']
                && $geometry['layers']['late_activities']['labels'] === ['Alerte locale']
                && str_contains($geometry['layers']['harvests']['labels'][0], '3,0 kg')
                && $geometry['layers']['alerts']['labels'] === ['1 activité(s) en retard']
                && abs($irrigation['used_m3'] - 4) < 1e-9
                && abs($irrigation['quota_m3'] - 20) < 1e-9
                && $irrigation['usage_percent'] === 20.0;
        });
    }

    public function test_les_couches_distinguent_les_activites_a_venir_des_retards_et_conservent_les_unites(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-TEMPS',
            'boundary' => $this->validBoundary(),
        ]);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
        ]);
        Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Récolte planifiée',
            'planned_date' => now()->addDays(5)->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ]);
        Activity::factory()->late()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Traitement oublié',
        ]);
        Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'harvested_at' => now()->subDay(),
            'quantity' => 12.5,
            'unit' => 'kg',
        ]);
        Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'harvested_at' => now()->subDays(2),
            'quantity' => 4,
            'unit' => 'botte',
        ]);

        $this->actingAsFarmMember($farm);

        $this->get(route('farm-map.index'))
            ->assertOk()
            ->assertSee('Activités à venir')
            ->assertSee('Activités en retard')
            ->assertViewHas('map', function (array $map) use ($unit): bool {
                $row = collect($map['units'])->firstWhere('unit.id', $unit->id);
                $layers = $row['layers'];

                return $layers['upcoming_activities']['count'] === 1
                    && $layers['upcoming_activities']['labels'] === ['Récolte planifiée']
                    && $layers['late_activities']['count'] === 1
                    && $layers['late_activities']['labels'] === ['Traitement oublié']
                    && $layers['harvests']['count'] === 2
                    && count($layers['harvests']['labels']) === 2
                    && str_contains($layers['harvests']['labels'][0], 'kg')
                    && str_contains($layers['harvests']['labels'][1], 'botte')
                    && $row['harvest_totals_by_unit'] == ['kg' => 12.5, 'botte' => 4.0];
            });
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
