<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\FarmRole;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\Farm;
use App\Models\IrrigationEvent;
use App\Models\ProductionUnit;
use App\Services\IrrigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class IrrigationTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_arrosage_est_enregistre_et_alimente_le_resume_de_la_campagne(): void
    {
        $farm = $this->createFarm();
        [$unit, $campaign] = $this->campaign($farm, waterQuota: 10, area: 100);
        $this->actingAsFarmMember($farm);

        $this->post(route('irrigation.store'), [
            'campaign_id' => $campaign->id,
            'production_unit_id' => $unit->id,
            'irrigated_at' => now()->toDateString(),
            'volume_m3' => 8.5,
            'duration_hours' => 2.5,
            'method' => 'goutte-à-goutte',
            'source' => 'forage',
        ])->assertRedirect(route('irrigation.index'));

        $this->assertDatabaseHas('irrigation_events', [
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'volume_m3' => 8.5,
        ]);

        $summary = app(IrrigationService::class)->summaryForCampaign($campaign->fresh());

        $this->assertSame(8.5, $summary['total_volume_m3']);
        $this->assertSame(0.085, $summary['volume_per_m2']);
        $this->assertSame('approaching', $summary['status']);
    }

    public function test_la_parcelle_doit_correspondre_a_la_campagne(): void
    {
        $farm = $this->createFarm();
        [$unit, $campaign] = $this->campaign($farm);
        $otherUnit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $this->actingAsFarmMember($farm);

        $this->from(route('irrigation.create'))
            ->post(route('irrigation.store'), [
                'campaign_id' => $campaign->id,
                'production_unit_id' => $otherUnit->id,
                'irrigated_at' => now()->toDateString(),
                'volume_m3' => 2,
            ])
            ->assertRedirect(route('irrigation.create'))
            ->assertSessionHasErrors('production_unit_id');

        $this->assertSame(0, IrrigationEvent::count());
    }

    public function test_les_ecrans_irrigation_sont_accessibles_aux_membres(): void
    {
        $farm = $this->createFarm();
        [$unit, $campaign] = $this->campaign($farm);
        IrrigationEvent::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'production_unit_id' => $unit->id,
            'volume_m3' => 3.25,
        ]);
        $this->actingAsFarmMember($farm);

        $this->get(route('irrigation.index'))->assertOk()->assertSee('3,250');
        $this->get(route('irrigation.create'))->assertOk()->assertSee($campaign->name);
        $this->get(route('irrigation.comparison'))->assertOk()->assertSee($campaign->name);
    }

    public function test_un_agent_peut_saisir_un_arrosage(): void
    {
        $farm = $this->createFarm();
        [$unit, $campaign] = $this->campaign($farm);
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->post(route('irrigation.store'), [
            'campaign_id' => $campaign->id,
            'production_unit_id' => $unit->id,
            'irrigated_at' => now()->toDateString(),
            'volume_m3' => 1.5,
        ])->assertRedirect(route('irrigation.index'));

        $this->assertSame(1, IrrigationEvent::count());
    }

    /**
     * @return array{0: ProductionUnit, 1: Campaign}
     */
    private function campaign(Farm $farm, float $waterQuota = 50, float $area = 250): array
    {
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => $area]);
        $crop = Crop::factory()->create(['farm_id' => $farm->id]);

        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'crop_id' => $crop->id,
            'cultivated_area_m2' => $area,
            'water_quota_m3' => $waterQuota,
            'water_alert_threshold_percent' => 80,
            'status' => CampaignStatus::Ongoing->value,
        ]);

        return [$unit, $campaign];
    }
}
