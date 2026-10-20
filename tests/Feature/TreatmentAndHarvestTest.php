<?php

namespace Tests\Feature;

use App\Enums\BaseUnit;
use App\Enums\CampaignStatus;
use App\Enums\ReferenceType;
use App\Exceptions\HarvestTooEarlyException;
use App\Exceptions\InsufficientStockException;
use App\Models\Campaign;
use App\Models\Input;
use App\Services\HarvestService;
use App\Services\StockService;
use App\Services\TreatmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class TreatmentAndHarvestTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    private function scenario(): array
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'status' => CampaignStatus::Ongoing->value,
            'reference_type' => ReferenceType::Planting->value,
        ]);

        $input = Input::factory()->phytosanitary()->create([
            'farm_id' => $farm->id,
            'base_unit' => BaseUnit::Liter->value,
            'quantity' => 0,
            'minimum_stock' => 0,
            'default_pre_harvest_days' => 14,
            'delay_source' => 'Notice fabricant',
        ]);

        $actor = $this->memberOf($farm);
        app(StockService::class)->receive($input, 10, $actor, 'Achat');

        return [$campaign, $input, $actor];
    }

    public function test_un_traitement_consomme_le_stock_et_fixe_la_borne_de_recolte(): void
    {
        [$campaign, $input, $actor] = $this->scenario();
        $appliedAt = now()->subDays(3);

        $treatment = app(TreatmentService::class)->record($campaign, $input, $actor, [
            'quantity' => 2,
            'dose_value' => 1.5,
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => 14,
            'applied_at' => $appliedAt->toDateTimeString(),
            'delay_source' => 'Notice fabricant',
        ]);

        $this->assertSame(8.0, (float) $input->fresh()->quantity);
        $this->assertSame(
            $appliedAt->copy()->addDays(14)->timestamp,
            $treatment->minimum_harvest_at->timestamp,
        );
        $this->assertDatabaseHas('activities', [
            'id' => $treatment->activity_id,
            'campaign_id' => $campaign->id,
            'type' => 'treatment',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'id' => $treatment->stock_movement_id,
            'type' => 'consumption',
        ]);
    }

    public function test_un_traitement_sans_stock_suffisant_est_refuse(): void
    {
        [$campaign, $input, $actor] = $this->scenario();

        $this->expectException(InsufficientStockException::class);

        app(TreatmentService::class)->record($campaign, $input, $actor, [
            'quantity' => 999,
            'dose_value' => 1,
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => 14,
            'applied_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_une_recolte_avant_la_borne_est_refusee(): void
    {
        [$campaign, $input, $actor] = $this->scenario();

        app(TreatmentService::class)->record($campaign, $input, $actor, [
            'quantity' => 1,
            'dose_value' => 1,
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => 14,
            'applied_at' => now()->toDateTimeString(),
        ]);

        $this->expectException(HarvestTooEarlyException::class);

        app(HarvestService::class)->record($campaign, $actor, [
            'quantity' => 30,
            'harvested_at' => now()->addDays(3)->toDateTimeString(),
        ]);
    }

    public function test_une_recolte_apres_la_borne_est_acceptee(): void
    {
        [$campaign, $input, $actor] = $this->scenario();

        app(TreatmentService::class)->record($campaign, $input, $actor, [
            'quantity' => 1,
            'dose_value' => 1,
            'dose_unit' => 'L/ha',
            'pre_harvest_days' => 14,
            'applied_at' => now()->subDays(20)->toDateTimeString(),
        ]);

        $harvest = app(HarvestService::class)->record($campaign, $actor, [
            'quantity' => 40,
            'harvested_at' => now()->toDateTimeString(),
        ]);

        $this->assertSame(40.0, (float) $harvest->quantity);
        $this->assertSame('kg', $harvest->unit);
    }
}
