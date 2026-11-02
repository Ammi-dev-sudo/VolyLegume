<?php

namespace Tests\Feature;

use App\Enums\BaseUnit;
use App\Enums\ExpenseCategory;
use App\Enums\FarmRole;
use App\Enums\InputCategory;
use App\Models\Campaign;
use App\Models\Expense;
use App\Models\Input;
use App\Models\Revenue;
use App\Models\Sale;
use App\Services\StockService;
use App\Services\TreatmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_l_export_des_campagnes_est_borne_a_l_exploitation_active(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        Campaign::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Campagne visible',
        ]);

        $otherFarm = $this->createFarm();
        Campaign::factory()->create([
            'farm_id' => $otherFarm->id,
            'name' => 'Campagne confidentielle',
        ]);

        $response = $this->get(route('exports.campaigns'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Campagne;Culture;Parcelle', $content);
        $this->assertStringContainsString('Campagne visible', $content);
        $this->assertStringNotContainsString('Campagne confidentielle', $content);
    }

    public function test_l_export_du_stock_indique_les_intrants_critiques(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        Input::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Fongicide cuivre',
            'category' => InputCategory::Phytosanitary->value,
            'base_unit' => BaseUnit::Liter->value,
            'quantity' => 3,
            'minimum_stock' => 5,
        ]);

        $response = $this->get(route('exports.stock'));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Fongicide cuivre', $content);
        $this->assertStringContainsString(';3;5;Oui;', $content);
    }

    public function test_l_export_des_traitements_reprend_les_delais(): void
    {
        $farm = $this->createFarm();
        $actor = $this->actingAsFarmMember($farm);

        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $input = Input::factory()->phytosanitary()->create([
            'farm_id' => $farm->id,
            'quantity' => 0,
            'minimum_stock' => 0,
        ]);
        app(StockService::class)->receive($input, 10, $actor, 'Achat');
        app(TreatmentService::class)->record($campaign, $input, $actor, [
            'quantity' => 1,
            'dose_value' => 1,
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => 14,
            'applied_at' => now()->toDateTimeString(),
            'delay_source' => 'Notice fabricant',
        ]);

        $response = $this->get(route('exports.treatments'));

        $response->assertOk();
        $this->assertStringContainsString('Notice fabricant', $response->streamedContent());
    }

    public function test_l_export_des_ventes_indique_le_reste_a_encaisser(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        Sale::factory()->create([
            'farm_id' => $farm->id,
            'reference' => 'VTE-TEST-001',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'amount_paid' => 40000,
        ]);

        $response = $this->get(route('exports.sales'));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('VTE-TEST-001', $content);
        $this->assertStringContainsString('100000;40000;60000', $content);
    }

    public function test_l_export_financier_fusionne_depenses_et_recettes(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);

        Expense::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'category' => ExpenseCategory::Inputs->value,
            'description' => 'Achat semences',
            'amount' => 25000,
        ]);

        Revenue::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'description' => 'Vente au marché',
            'amount' => 90000,
        ]);

        $response = $this->get(route('exports.finances'));

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Dépense', $content);
        $this->assertStringContainsString('Recette', $content);
        $this->assertStringContainsString('Achat semences', $content);
        $this->assertStringContainsString('Vente au marché', $content);
    }

    public function test_un_agent_peut_exporter_les_etats_de_son_exploitation(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('exports.campaigns'))->assertOk();
        $this->get(route('exports.stock'))->assertOk();
        $this->get(route('exports.sales'))->assertOk();
        $this->get(route('exports.finances'))->assertOk();
    }
}
