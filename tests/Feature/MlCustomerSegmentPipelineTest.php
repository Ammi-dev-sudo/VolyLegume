<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\Prediction;
use App\Models\Sale;
use App\Models\User;
use App\Services\Ml\CustomerSegmentDatasetBuilder;
use App\Services\Ml\CustomerSegmentModelRunner;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class MlCustomerSegmentPipelineTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_le_builder_calcule_les_variables_rfm(): void
    {
        $farm = $this->createFarm();
        $actor = $this->memberOf($farm);

        $recent = $this->customer($farm, 'Client récent');
        $inactive = $this->customer($farm, 'Client inactif');

        $this->sale($farm, $recent, $actor, 100000, now()->subDays(5));
        $this->sale($farm, $recent, $actor, 50000, now()->subDays(2));

        $rows = collect(app(CustomerSegmentDatasetBuilder::class)->trainingRows())
            ->keyBy('sample_id');

        $this->assertSame(2, $rows[$recent->id]['frequency']);
        $this->assertSame(150000.0, $rows[$recent->id]['monetary']);
        $this->assertSame(2, $rows[$recent->id]['recency_days']);
        $this->assertSame(0, $rows[$inactive->id]['frequency']);
        $this->assertSame(3650, $rows[$inactive->id]['recency_days']);
    }

    public function test_le_pipeline_segmente_et_importe_les_clients(): void
    {
        if (! app(CustomerSegmentModelRunner::class)->available()) {
            $this->markTestSkipped('Python 3 est indisponible dans cet environnement.');
        }

        $farm = $this->createFarm();
        $actor = $this->memberOf($farm);

        $profiles = [
            ['Client fidèle', 3, 100000.0, 5],
            ['Client régulier', 2, 60000.0, 20],
            ['Client occasionnel', 1, 25000.0, 80],
            ['Client ancien', 1, 15000.0, 200],
            ['Client récent', 1, 90000.0, 3],
            ['Client inactif', 0, 0.0, null],
        ];

        foreach ($profiles as [$name, $count, $amount, $days]) {
            $customer = $this->customer($farm, $name);

            if ($count === 0) {
                continue;
            }

            for ($index = 0; $index < $count; $index++) {
                $this->sale($farm, $customer, $actor, $amount, now()->subDays($days + $index * 7));
            }
        }

        $directory = storage_path('app/ml-test-'.uniqid('', true));
        File::ensureDirectoryExists($directory);

        $dataset = $directory.'/dataset.csv';
        $model = $directory.'/model.json';
        $metrics = $directory.'/metrics.json';
        $output = $directory.'/predictions.csv';

        $this->artisan('ml:export', ['useCase' => 'customer-segment', '--path' => $dataset])->assertSuccessful();
        $this->artisan('ml:train', [
            'useCase' => 'customer-segment',
            '--dataset' => $dataset,
            '--model' => $model,
            '--metrics' => $metrics,
        ])->assertSuccessful();

        $this->assertFileExists($model);
        $this->assertFileExists($metrics);

        $this->artisan('ml:predict', [
            'useCase' => 'customer-segment',
            '--model' => $model,
            '--output' => $output,
        ])->assertSuccessful();

        $predictions = Prediction::query()->where('model', 'customer_segment')->get();

        $this->assertCount(6, $predictions);
        $this->assertTrue($predictions->every(
            fn (Prediction $prediction): bool => in_array($prediction->label, [
                'Clients fidèles',
                'Clients occasionnels',
                'Clients à relancer',
                'Clients inactifs',
                'Clients',
            ], true),
        ));
        $this->assertArrayHasKey('monetary', $predictions->first()->context);
        $this->assertArrayHasKey('segment', $predictions->first()->context);

        File::deleteDirectory($directory);
    }

    public function test_le_tableau_de_bord_reserve_les_segments_aux_roles_financiers(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);
        $customer = $this->customer($farm, 'Client test');

        Prediction::query()->create([
            'farm_id' => $farm->id,
            'model' => 'customer_segment',
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'label' => 'Clients fidèles',
            'value' => 0.5,
            'score' => null,
            'context' => [
                'segment' => 'Clients fidèles',
                'recency_days' => 5,
                'frequency' => 3,
                'monetary' => 120000,
            ],
            'generated_at' => now(),
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Segments clients')
            ->assertSee('Clients fidèles');

        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Segments clients');
    }

    private function customer(Farm $farm, string $name): Customer
    {
        return Customer::factory()->create([
            'farm_id' => $farm->id,
            'name' => $name,
        ]);
    }

    private function sale(Farm $farm, Customer $customer, User $actor, float $amount, CarbonInterface $date): Sale
    {
        return Sale::factory()->create([
            'farm_id' => $farm->id,
            'customer_id' => $customer->id,
            'sale_date' => $date->toDateString(),
            'subtotal' => $amount,
            'total_amount' => $amount,
            'amount_paid' => $amount,
            'recorded_by' => $actor->id,
        ]);
    }
}
