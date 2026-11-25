<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Farm;
use App\Models\Harvest;
use App\Models\Prediction;
use App\Models\ProductionUnit;
use App\Models\User;
use App\Services\Ml\YieldDatasetBuilder;
use App\Services\Ml\YieldModelRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class MlYieldPipelineTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_le_builder_construit_des_lignes_de_rendement_sans_fuite(): void
    {
        $farm = $this->createFarm();
        $actor = $this->memberOf($farm);

        $this->harvestedCampaign($farm, $actor, quantity: 300, area: 100);

        $rows = app(YieldDatasetBuilder::class)->trainingRows();

        $this->assertCount(1, $rows);
        $this->assertSame(3.0, round((float) $rows[0]['target'], 4));
        $this->assertSame(100.0, round((float) $rows[0]['cultivated_area_m2'], 4));
        $this->assertArrayHasKey('crop_category', $rows[0]);
        $this->assertArrayNotHasKey('actual_yield_kg', $rows[0]);
    }

    public function test_la_commande_export_ecrit_un_csv(): void
    {
        $farm = $this->createFarm();
        $actor = $this->memberOf($farm);
        $this->harvestedCampaign($farm, $actor, quantity: 250, area: 100);

        $path = storage_path('app/ml-test/export-'.uniqid('', true).'.csv');

        $this->artisan('ml:export', ['useCase' => 'yield', '--path' => $path])->assertSuccessful();

        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('sample_id', $content);
        $this->assertStringContainsString('target', $content);

        File::delete($path);
    }

    public function test_le_pipeline_entraine_le_modele_et_importe_les_previsions(): void
    {
        if (! app(YieldModelRunner::class)->available()) {
            $this->markTestSkipped('Python 3 est indisponible dans cet environnement.');
        }

        $farm = $this->createFarm();
        $actor = $this->memberOf($farm);

        foreach ([
            [200.0, 100.0],
            [260.0, 100.0],
            [320.0, 100.0],
            [180.0, 100.0],
            [300.0, 100.0],
            [240.0, 100.0],
        ] as [$quantity, $area]) {
            $this->harvestedCampaign($farm, $actor, $quantity, $area);
        }

        $active = $this->activeCampaign($farm);

        $directory = storage_path('app/ml-test-'.uniqid('', true));
        File::ensureDirectoryExists($directory);

        $dataset = $directory.'/dataset.csv';
        $model = $directory.'/model.json';
        $metrics = $directory.'/metrics.json';
        $output = $directory.'/predictions.csv';

        $this->artisan('ml:export', ['useCase' => 'yield', '--path' => $dataset])->assertSuccessful();
        $this->artisan('ml:train', [
            'useCase' => 'yield',
            '--dataset' => $dataset,
            '--model' => $model,
            '--metrics' => $metrics,
        ])->assertSuccessful();

        $this->assertFileExists($model);
        $this->assertFileExists($metrics);

        $this->artisan('ml:predict', [
            'useCase' => 'yield',
            '--model' => $model,
            '--output' => $output,
        ])->assertSuccessful();

        $prediction = Prediction::query()
            ->where('model', 'yield')
            ->where('subject_type', Campaign::class)
            ->where('subject_id', $active->id)
            ->first();

        $this->assertNotNull($prediction);
        $this->assertGreaterThanOrEqual(0.0, (float) $prediction->value);
        $this->assertArrayHasKey('predicted_total_kg', $prediction->context);

        File::deleteDirectory($directory);
    }

    private function harvestedCampaign(Farm $farm, User $actor, float $quantity, float $area): Campaign
    {
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'area_m2' => $area,
            'soil_type' => 'limoneux',
            'irrigation_type' => 'goutte-à-goutte',
            'sun_exposure' => 'plein soleil',
            'slope' => 'faible',
        ]);

        $crop = Crop::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Tomate',
            'variety' => 'tomate-'.uniqid(),
            'estimated_cycle_days' => 90,
            'average_yield_kg_m2' => 3.0,
        ]);

        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'cultivated_area_m2' => $area,
            'start_date' => now()->subDays(80)->toDateString(),
            'status' => CampaignStatus::Ongoing->value,
        ]);

        Harvest::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'quantity' => $quantity,
            'harvested_area_m2' => $area,
            'recorded_by' => $actor->id,
            'harvested_at' => now(),
        ]);

        return $campaign;
    }

    private function activeCampaign(Farm $farm): Campaign
    {
        $unit = ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'area_m2' => 120,
            'soil_type' => 'limoneux',
            'irrigation_type' => 'goutte-à-goutte',
            'sun_exposure' => 'plein soleil',
            'slope' => 'faible',
        ]);

        $crop = Crop::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Laitue',
            'variety' => 'laitue-'.uniqid(),
            'estimated_cycle_days' => 60,
            'average_yield_kg_m2' => 2.5,
        ]);

        return Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'cultivated_area_m2' => 120,
            'start_date' => now()->subDays(30)->toDateString(),
            'status' => CampaignStatus::Ongoing->value,
        ]);
    }
}
