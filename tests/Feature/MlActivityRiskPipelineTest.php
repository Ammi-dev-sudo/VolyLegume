<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\CampaignStatus;
use App\Enums\FarmRole;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Farm;
use App\Models\Prediction;
use App\Models\User;
use App\Services\Ml\ActivityRiskDatasetBuilder;
use App\Services\Ml\ActivityRiskModelRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class MlActivityRiskPipelineTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_le_builder_etiquette_les_activites_realisees_et_en_retard(): void
    {
        $farm = $this->createFarm();
        $actor = $this->memberOf($farm);
        $campaign = $this->campaign($farm);

        $this->activity($campaign, $actor, [
            'planned_date' => now()->subDays(10)->toDateString(),
            'performed_at' => now()->subDays(10)->setTime(9, 0),
            'status' => ActivityStatus::Completed->value,
        ]);
        $this->activity($campaign, $actor, [
            'planned_date' => now()->subDays(10)->toDateString(),
            'performed_at' => now()->subDays(8)->setTime(9, 0),
            'status' => ActivityStatus::Completed->value,
        ]);
        $this->activity($campaign, $actor, [
            'planned_date' => now()->subDays(3)->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ]);
        $this->activity($campaign, $actor, [
            'planned_date' => now()->addDays(3)->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ]);
        $this->activity($campaign, $actor, [
            'planned_date' => now()->addDays(3)->toDateString(),
            'status' => ActivityStatus::Cancelled->value,
        ]);

        $builder = app(ActivityRiskDatasetBuilder::class);

        $trainingRows = $builder->trainingRows();
        $labels = collect($trainingRows)->pluck('late')->sort()->values()->all();

        $this->assertCount(3, $trainingRows);
        $this->assertSame([0, 1, 1], $labels);
        $this->assertCount(1, $builder->predictionRows());
    }

    public function test_le_pipeline_entraine_et_importe_les_risques_de_retard(): void
    {
        if (! app(ActivityRiskModelRunner::class)->available()) {
            $this->markTestSkipped('Python 3 est indisponible dans cet environnement.');
        }

        $farm = $this->createFarm();
        $actor = $this->memberOf($farm);
        $campaign = $this->campaign($farm);

        for ($index = 0; $index < 6; $index++) {
            $this->activity($campaign, $actor, [
                'planned_date' => now()->subDays(30 + $index)->toDateString(),
                'performed_at' => now()->subDays(30 + $index)->setTime(9, 0),
                'status' => ActivityStatus::Completed->value,
            ]);
        }

        for ($index = 0; $index < 4; $index++) {
            $this->activity($campaign, $actor, [
                'planned_date' => now()->subDays(30 + $index)->toDateString(),
                'performed_at' => now()->subDays(27 + $index)->setTime(9, 0),
                'status' => ActivityStatus::Completed->value,
            ]);
        }

        $future = $this->activity($campaign, $actor, [
            'title' => 'Désherbage à venir',
            'planned_date' => now()->addDays(5)->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ]);

        $directory = storage_path('app/ml-test-'.uniqid('', true));
        File::ensureDirectoryExists($directory);

        $dataset = $directory.'/dataset.csv';
        $model = $directory.'/model.json';
        $metrics = $directory.'/metrics.json';
        $output = $directory.'/predictions.csv';

        $this->artisan('ml:export', ['useCase' => 'activity-risk', '--path' => $dataset])->assertSuccessful();
        $this->artisan('ml:train', [
            'useCase' => 'activity-risk',
            '--dataset' => $dataset,
            '--model' => $model,
            '--metrics' => $metrics,
        ])->assertSuccessful();

        $this->assertFileExists($model);
        $this->assertFileExists($metrics);

        $this->artisan('ml:predict', [
            'useCase' => 'activity-risk',
            '--model' => $model,
            '--output' => $output,
        ])->assertSuccessful();

        $prediction = Prediction::query()
            ->where('model', 'activity_risk')
            ->where('subject_type', Activity::class)
            ->where('subject_id', $future->id)
            ->first();

        $this->assertNotNull($prediction);
        $this->assertGreaterThanOrEqual(0.0, (float) $prediction->value);
        $this->assertLessThanOrEqual(1.0, (float) $prediction->value);
        $this->assertArrayHasKey('risk_level', $prediction->context);

        File::deleteDirectory($directory);
    }

    public function test_le_tableau_de_bord_affiche_les_activites_a_risque(): void
    {
        $farm = $this->createFarm();
        $actor = $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $campaign = $this->campaign($farm);

        $activity = $this->activity($campaign, $actor, [
            'title' => 'Traitement sensible',
            'planned_date' => now()->addDays(4)->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ]);

        Prediction::query()->create([
            'farm_id' => $farm->id,
            'model' => 'activity_risk',
            'subject_type' => Activity::class,
            'subject_id' => $activity->id,
            'label' => 'Risque de retard',
            'value' => 0.82,
            'score' => 0.82,
            'context' => ['risk_level' => 'eleve', 'probability' => 0.82],
            'generated_at' => now(),
        ]);

        $response = $this->get(route('dashboard'))->assertOk();

        $response->assertSee('Activités à risque');
        $response->assertSee('Traitement sensible');
    }

    private function campaign(Farm $farm): Campaign
    {
        return Campaign::factory()->create([
            'farm_id' => $farm->id,
            'start_date' => now()->subDays(40)->toDateString(),
            'status' => CampaignStatus::Ongoing->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function activity(Campaign $campaign, User $actor, array $attributes): Activity
    {
        return Activity::factory()->create(array_merge([
            'campaign_id' => $campaign->id,
            'farm_id' => $campaign->farm_id,
            'created_by' => $actor->id,
            'status' => ActivityStatus::Completed->value,
        ], $attributes));
    }
}
