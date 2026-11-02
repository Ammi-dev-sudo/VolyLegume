<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\ExpenseCategory;
use App\Enums\RevenueCategory;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\IrrigationEvent;
use App\Models\ProductionUnit;
use App\Models\Revenue;
use App\Services\ProfitabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ProfitabilityTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    /**
     * @return array{campaign: Campaign, parcel: ProductionUnit, crop: Crop}
     */
    private function scenario(): array
    {
        $farm = $this->createFarm();
        $parcel = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P01',
            'name' => 'Parcelle P01',
        ]);
        $crop = Crop::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Tomate',
        ]);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $parcel->id,
            'crop_id' => $crop->id,
            'name' => 'Tomate P01 — campagne test',
            'status' => CampaignStatus::Ongoing->value,
            'cultivated_area_m2' => 100,
            'start_date' => now()->subDays(40)->toDateString(),
            'expected_end_date' => now()->addDays(40)->toDateString(),
        ]);

        Expense::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'category' => ExpenseCategory::Inputs->value,
            'amount' => 50000,
            'expense_date' => now()->subDays(10)->toDateString(),
        ]);

        Revenue::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'category' => RevenueCategory::HarvestSale->value,
            'amount' => 150000,
            'revenue_date' => now()->subDays(5)->toDateString(),
        ]);

        Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'quantity' => 200,
            'harvested_at' => now()->subDays(5),
        ]);

        IrrigationEvent::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'production_unit_id' => $parcel->id,
            'volume_m3' => 40,
            'irrigated_at' => now()->subDays(6),
        ]);

        return [$campaign, $parcel, $crop];
    }

    public function test_les_indicateurs_sont_agreges_par_culture_et_par_parcelle(): void
    {
        [$campaign] = $this->scenario();

        $data = app(ProfitabilityService::class)->forFarm($campaign->farm);

        $this->assertSame(1, $data['totals']['campaigns']);
        $this->assertSame(50000.0, $data['totals']['expenses']);
        $this->assertSame(150000.0, $data['totals']['revenues']);
        $this->assertSame(100000.0, $data['totals']['margin']);
        $this->assertSame(1000.0, $data['totals']['margin_per_m2']);
        $this->assertSame(2.0, $data['totals']['yield_kg_per_m2']);
        $this->assertSame(20000.0, $data['totals']['yield_kg_per_ha']);
        $this->assertSame(0.2, $data['totals']['water_per_kg']);

        $this->assertSame('Tomate', $data['crops'][0]['label']);
        $this->assertSame('Parcelle P01', $data['parcels'][0]['label']);
    }

    public function test_l_ecran_de_rentabilite_est_accessible_aux_membres(): void
    {
        [$campaign] = $this->scenario();
        $this->actingAsFarmMember($campaign->farm);

        $this->get(route('profitability.index'))
            ->assertOk()
            ->assertSee('Rentabilité par culture et par parcelle')
            ->assertSee('Tomate P01 — campagne test')
            ->assertSee('100 000 Ar');
    }

    public function test_l_export_csv_contient_les_lignes_de_la_campagne(): void
    {
        [$campaign] = $this->scenario();
        $this->actingAsFarmMember($campaign->farm);

        $response = $this->get(route('profitability.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Campagne;Culture;Parcelle', $content);
        $this->assertStringContainsString('Tomate P01 — campagne test', $content);
        $this->assertStringContainsString(';Tomate;', $content);
        $this->assertStringContainsString('50000;150000;100000', $content);
    }

    public function test_une_campagne_d_une_autre_exploitation_n_est_pas_agregee(): void
    {
        [$campaign] = $this->scenario();

        $otherFarm = $this->createFarm();
        $otherCampaign = Campaign::factory()->create([
            'farm_id' => $otherFarm->id,
            'cultivated_area_m2' => 500,
        ]);
        Expense::factory()->create([
            'farm_id' => $otherFarm->id,
            'campaign_id' => $otherCampaign->id,
            'amount' => 999999,
        ]);

        $data = app(ProfitabilityService::class)->forFarm($campaign->farm);

        $this->assertSame(1, $data['totals']['campaigns']);
        $this->assertSame(50000.0, $data['totals']['expenses']);
    }

    public function test_le_filtre_par_campagne_restreint_les_indicateurs(): void
    {
        [$campaign] = $this->scenario();

        $second = Campaign::factory()->create([
            'farm_id' => $campaign->farm_id,
            'cultivated_area_m2' => 50,
        ]);
        Expense::factory()->create([
            'farm_id' => $campaign->farm_id,
            'campaign_id' => $second->id,
            'amount' => 10000,
        ]);

        $data = app(ProfitabilityService::class)->forFarm($campaign->farm, [
            'campaign_id' => $campaign->id,
        ]);

        $this->assertSame(1, $data['totals']['campaigns']);
        $this->assertSame(50000.0, $data['totals']['expenses']);
    }
}
