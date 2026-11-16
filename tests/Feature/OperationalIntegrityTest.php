<?php

namespace Tests\Feature;

use App\Enums\BaseUnit;
use App\Enums\CampaignStatus;
use App\Enums\FarmStatus;
use App\Enums\ProductionUnitStatus;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Input;
use App\Models\ProduceLot;
use App\Models\ProductionUnit;
use App\Models\ProductionUnitZone;
use App\Services\HarvestService;
use App\Services\StockService;
use App\Services\TreatmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class OperationalIntegrityTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_une_exploitation_desactivee_ne_donne_plus_acces_aux_routes_metier(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);
        $farm->update(['status' => FarmStatus::Inactive->value]);

        $this->get(route('production-units.index'))
            ->assertRedirect(route('farms.index'))
            ->assertSessionHasErrors('farm');

        $this->post(route('farms.switch', $farm))
            ->assertRedirect(route('farms.index'))
            ->assertSessionHasErrors('farm');
    }

    public function test_la_superficie_de_l_exploitation_ne_peut_pas_descendre_sous_la_somme_des_parcelles(): void
    {
        $farm = $this->createFarm(['area_m2' => 1000]);
        $user = $this->actingAsFarmMember($farm);
        ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => 800]);

        $this->put(route('farms.update', $farm), [
            'name' => $farm->name,
            'location' => $farm->location,
            'area_m2' => 500,
            'status' => FarmStatus::Active->value,
            'manager_id' => $user->id,
        ])->assertSessionHasErrors('area_m2');

        $this->assertSame(1000.0, (float) $farm->fresh()->area_m2);
    }

    public function test_la_superficie_d_une_parcelle_ne_peut_pas_descendre_sous_la_somme_de_ses_zones(): void
    {
        $farm = $this->createFarm(['area_m2' => 1000]);
        $this->actingAsFarmMember($farm);
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => 100]);

        ProductionUnitZone::create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'code' => 'Z1',
            'name' => 'Zone principale',
            'area_m2' => 60,
            'is_active' => true,
        ]);

        $this->put(route('production-units.update', $unit), [
            'code' => $unit->code,
            'name' => $unit->name,
            'area_m2' => 50,
            'location' => $unit->location,
            'status' => ProductionUnitStatus::Available->value,
        ])->assertSessionHasErrors('area_m2');

        $this->assertSame(100.0, (float) $unit->fresh()->area_m2);
    }

    public function test_les_campagnes_actives_ne_peuvent_pas_depasser_la_surface_disponible_sur_une_periode(): void
    {
        $farm = $this->createFarm(['area_m2' => 1000]);
        $this->actingAsFarmMember($farm);
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => 100]);
        $crop = Crop::factory()->create(['farm_id' => $farm->id]);

        Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'start_date' => '2026-01-01',
            'expected_end_date' => '2026-03-01',
            'cultivated_area_m2' => 60,
            'status' => CampaignStatus::Planned->value,
        ]);

        $payload = [
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'name' => 'Campagne en chevauchement',
            'start_date' => '2026-02-01',
            'expected_end_date' => '2026-04-01',
            'reference_type' => 'sowing',
            'cultivated_area_m2' => 50,
            'status' => CampaignStatus::Planned->value,
        ];

        $this->post(route('campaigns.store'), $payload)
            ->assertSessionHasErrors('cultivated_area_m2');

        $payload['name'] = 'Campagne décalée';
        $payload['start_date'] = '2026-04-01';
        $payload['expected_end_date'] = '2026-06-01';

        $this->post(route('campaigns.store'), $payload)
            ->assertRedirect(route('campaigns.index'));

        $this->assertDatabaseHas('campaigns', ['name' => 'Campagne décalée']);
    }

    public function test_les_pertes_ne_deviennent_pas_du_stock_commercialisable(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $actor = $this->memberOf($farm);

        $harvest = app(HarvestService::class)->record($campaign, $actor, [
            'quantity' => 100,
            'unit' => 'kg',
            'harvested_at' => now()->subHour()->toDateTimeString(),
            'loss_quantity' => 15,
        ]);

        $lot = $harvest->produceLot;

        $this->assertNotNull($lot);
        $this->assertSame(85.0, $harvest->marketableQuantity());
        $this->assertSame(85.0, (float) $lot->initial_quantity);
        $this->assertSame(85.0, (float) $lot->available_quantity);
        $this->assertDatabaseHas('produce_movements', [
            'produce_lot_id' => $lot->id,
            'type' => 'harvest',
            'quantity_delta' => 85,
        ]);
    }

    public function test_le_stock_affiche_les_totaux_par_unite_sans_les_melanger(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        ProduceLot::factory()->create([
            'farm_id' => $farm->id,
            'unit' => 'kg',
            'initial_quantity' => 12,
            'available_quantity' => 12,
        ]);
        ProduceLot::factory()->create([
            'farm_id' => $farm->id,
            'unit' => 'botte',
            'initial_quantity' => 8,
            'available_quantity' => 8,
        ]);

        $this->get(route('produce-lots.index'))
            ->assertOk()
            ->assertSee('12,000 kg')
            ->assertSee('8,000 botte')
            ->assertDontSee('20,000 kg');
    }

    public function test_la_fiche_traitement_affiche_la_quantite_reellement_consommee(): void
    {
        $farm = $this->createFarm();
        $actor = $this->actingAsFarmMember($farm);
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $input = Input::factory()->phytosanitary()->create([
            'farm_id' => $farm->id,
            'base_unit' => BaseUnit::Liter->value,
            'quantity' => 0,
            'minimum_stock' => 0,
        ]);

        app(StockService::class)->receive($input, 10, $actor, 'Achat');

        $treatment = app(TreatmentService::class)->record($campaign, $input, $actor, [
            'quantity' => 2,
            'dose_value' => 1.5,
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => 14,
            'applied_at' => now()->subDays(2)->toDateTimeString(),
            'delay_source' => 'Notice fabricant',
        ]);

        $this->get(route('treatments.show', $treatment))
            ->assertOk()
            ->assertSee('Quantité consommée')
            ->assertSee('2,000 L')
            ->assertSee('8,000 L');
    }

    public function test_l_unite_de_recolte_doit_etre_parmi_les_unites_autorisees(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);

        $this->post(route('harvests.store'), [
            'campaign_id' => $campaign->id,
            'quantity' => 10,
            'unit' => 'unite-inconnue',
            'harvested_at' => now()->toDateTimeString(),
        ])->assertSessionHasErrors('unit');
    }
}
