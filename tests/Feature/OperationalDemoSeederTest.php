<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropIssue;
use App\Models\IrrigationEvent;
use App\Models\ProductionUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class OperationalDemoSeederTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_le_jeu_de_donnees_operationnelles_est_complet_et_idempotent(): void
    {
        $farm = $this->createFarm(['name' => 'Ferme Ambohipo']);

        foreach (range(1, 3) as $index) {
            $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
            $crop = Crop::factory()->create(['farm_id' => $farm->id]);
            Campaign::factory()->create([
                'farm_id' => $farm->id,
                'production_unit_id' => $unit->id,
                'crop_id' => $crop->id,
                'cultivated_area_m2' => 250,
                'water_quota_m3' => 50,
                'status' => CampaignStatus::Ongoing->value,
            ]);
        }

        $this->artisan('demo:populate-operations', ['farm' => 'Ferme Ambohipo'])->assertExitCode(0);
        $this->artisan('demo:populate-operations', ['farm' => 'Ferme Ambohipo'])->assertExitCode(0);

        $this->assertSame(42, IrrigationEvent::query()->where('farm_id', $farm->id)->count());
        $this->assertSame(10, CropIssue::query()->where('farm_id', $farm->id)->count());
        $this->assertSame(10, CropIssue::query()->where('farm_id', $farm->id)->distinct()->count('reference'));
        $this->assertDatabaseHas('irrigation_events', [
            'farm_id' => $farm->id,
            'recorded_by' => $farm->manager_id,
            'notes' => 'Donnée démo VolyLegume : irrigation 01',
        ]);

        $this->actingAsFarmMember($farm);
        $this->get(route('irrigation.index'))
            ->assertOk()
            ->assertSee('Rythme des 7 derniers jours')
            ->assertSee('Irrigation &amp; eau', false);
        $this->get(route('issues.index'))
            ->assertOk()
            ->assertSee('Problèmes agricoles')
            ->assertSee('Insectes observés sur jeunes feuilles — démo');
    }

    public function test_la_commande_exige_une_confirmation_en_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('demo:populate-operations', ['farm' => 'Ferme Ambohipo'])
            ->expectsOutputToContain('relancez avec --force')
            ->assertExitCode(1);

        $this->assertSame(0, IrrigationEvent::count());
        $this->assertSame(0, CropIssue::count());
    }

    public function test_la_commande_confirmee_en_production_remplit_uniquement_ambohipo(): void
    {
        $farm = $this->createFarm(['name' => 'Ferme Ambohipo']);
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $crop = Crop::factory()->create(['farm_id' => $farm->id]);
        Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'cultivated_area_m2' => 250,
            'water_quota_m3' => 50,
            'status' => CampaignStatus::Ongoing->value,
        ]);
        $otherFarm = $this->createFarm(['name' => 'Exploitation Itasy']);

        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('demo:populate-operations', [
            'farm' => 'Ferme Ambohipo',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame(42, IrrigationEvent::query()->where('farm_id', $farm->id)->count());
        $this->assertSame(10, CropIssue::query()->where('farm_id', $farm->id)->count());
        $this->assertSame(0, IrrigationEvent::query()->where('farm_id', $otherFarm->id)->count());
        $this->assertSame(0, CropIssue::query()->where('farm_id', $otherFarm->id)->count());
    }
}
