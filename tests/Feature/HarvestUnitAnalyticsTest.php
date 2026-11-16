<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Harvest;
use App\Models\ProduceLot;
use App\Models\ProductionUnit;
use App\Services\DashboardService;
use App\Services\ProfitabilityService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class HarvestUnitAnalyticsTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    /**
     * @return array{campaign: Campaign, harvest_kg: Harvest, harvest_botte: Harvest}
     */
    private function scenario(): array
    {
        $farm = $this->createFarm();
        $parcel = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'area_m2' => 100,
        ]);
        $crop = Crop::factory()->create([
            'farm_id' => $farm->id,
            'production_unit' => 'kg',
        ]);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $parcel->id,
            'crop_id' => $crop->id,
            'status' => CampaignStatus::Ongoing->value,
            'cultivated_area_m2' => 100,
            'start_date' => now()->subDays(20)->toDateString(),
            'expected_end_date' => now()->addDays(20)->toDateString(),
        ]);

        $harvestKg = Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'quantity' => 200,
            'unit' => 'kg',
            'harvested_at' => now()->subDay(),
        ]);
        $harvestBotte = Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'quantity' => 30,
            'unit' => 'botte',
            'harvested_at' => now()->subDay(),
        ]);

        ProduceLot::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'harvest_id' => $harvestKg->id,
            'code' => 'LOT-KG',
            'initial_quantity' => 120,
            'available_quantity' => 120,
            'unit' => 'kg',
        ]);
        ProduceLot::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'harvest_id' => $harvestBotte->id,
            'code' => 'LOT-BOTTE',
            'initial_quantity' => 30,
            'available_quantity' => 30,
            'unit' => 'botte',
        ]);

        return [$campaign, $harvestKg, $harvestBotte];
    }

    public function test_les_indicateurs_de_rendement_ne_melangent_pas_les_unites_de_recolte(): void
    {
        [$campaign] = $this->scenario();
        $farm = $campaign->farm;

        $profitability = app(ProfitabilityService::class)->forFarm($farm);
        $this->assertSame(200.0, $profitability['totals']['harvest_kg']);
        $this->assertSame(['botte' => 30.0, 'kg' => 200.0], $profitability['totals']['harvest_units']);
        $this->assertSame(2.0, $profitability['totals']['yield_kg_per_m2']);

        $dashboard = app(DashboardService::class)->forFarm($farm);
        $this->assertSame(200.0, $dashboard['harvested_quantity']);
        $this->assertSame(['botte' => 30.0, 'kg' => 200.0], $dashboard['harvest_units']);
        $this->assertSame(20000.0, $dashboard['yield_kg_per_ha']);
        $this->assertSame(120.0, $dashboard['produce_available']);
        $this->assertSame(['botte' => 30.0, 'kg' => 120.0], $dashboard['produce_units']);

        $report = app(ReportService::class)->build($farm, now()->startOfYear(), now()->endOfDay());
        $this->assertSame(200.0, $report['summary']['harvested_quantity']);
        $this->assertSame(
            ['botte' => 30.0, 'kg' => 200.0],
            array_column($report['harvests_by_unit'], 'quantity', 'unit'),
        );
        $this->assertContains(
            ['Récolte (botte)', 30.0],
            app(ReportService::class)->sheets($report)['Synthèse'],
        );
    }

    public function test_une_recolte_en_botte_est_acceptee_et_conservee_avec_son_unite(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'status' => CampaignStatus::Ongoing->value,
        ]);

        $response = $this->post(route('harvests.store'), [
            'campaign_id' => $campaign->id,
            'quantity' => 12,
            'unit' => 'botte',
            'harvested_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('campaigns.show', $campaign));

        $this->assertDatabaseHas('harvests', [
            'campaign_id' => $campaign->id,
            'quantity' => 12,
            'unit' => 'botte',
        ]);
    }
}
