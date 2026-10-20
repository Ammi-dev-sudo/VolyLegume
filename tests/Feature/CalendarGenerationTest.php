<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\CalendarStep;
use App\Models\Campaign;
use App\Services\CropCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class CalendarGenerationTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    private function campaignWithSteps(): array
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'start_date' => '2026-01-01',
        ]);

        CalendarStep::create([
            'farm_id' => $farm->id,
            'crop_id' => $campaign->crop_id,
            'name' => 'Semis',
            'activity_type' => ActivityType::Sowing->value,
            'offset_days' => 0,
            'position' => 1,
            'is_active' => true,
        ]);

        CalendarStep::create([
            'farm_id' => $farm->id,
            'crop_id' => $campaign->crop_id,
            'name' => 'Désherbage',
            'activity_type' => ActivityType::Weeding->value,
            'offset_days' => 21,
            'position' => 2,
            'is_active' => true,
        ]);

        return [$campaign, $this->memberOf($farm)];
    }

    public function test_la_generation_cree_les_activites_avec_les_dates_calculees(): void
    {
        [$campaign, $actor] = $this->campaignWithSteps();

        $result = app(CropCalendarService::class)->generate($campaign, $actor);

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, $campaign->activities()->count());

        $weeding = $campaign->activities()->where('title', 'Désherbage')->firstOrFail();
        $this->assertSame('2026-01-22', $weeding->planned_date->toDateString());
        $this->assertSame(ActivityStatus::Planned, $weeding->status);
    }

    public function test_la_generation_est_idempotente(): void
    {
        [$campaign, $actor] = $this->campaignWithSteps();
        $service = app(CropCalendarService::class);

        $service->generate($campaign, $actor);
        $result = $service->generate($campaign, $actor);

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['updated']);
        $this->assertSame(2, $campaign->activities()->count());
    }

    public function test_les_activites_realisees_sont_preservees(): void
    {
        [$campaign, $actor] = $this->campaignWithSteps();
        $service = app(CropCalendarService::class);

        $service->generate($campaign, $actor);
        $done = $campaign->activities()->where('title', 'Semis')->firstOrFail();
        $done->update(['status' => ActivityStatus::Completed, 'performed_at' => now()]);

        $result = $service->generate($campaign, $actor);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['preserved']);
        $this->assertSame(ActivityStatus::Completed, $done->fresh()->status);
    }

    public function test_la_replanification_ne_touche_pas_aux_activites_realisees(): void
    {
        [$campaign, $actor] = $this->campaignWithSteps();
        $service = app(CropCalendarService::class);
        $service->generate($campaign, $actor);

        $done = $campaign->activities()->where('title', 'Semis')->firstOrFail();
        $done->update(['status' => ActivityStatus::Completed, 'performed_at' => now()]);

        $campaign->update(['start_date' => '2026-02-01']);

        $count = $service->replan($campaign);

        $this->assertSame(1, $count);
        $this->assertSame('2026-02-22', Activity::query()->where('title', 'Désherbage')->firstOrFail()->planned_date->toDateString());
        $this->assertSame('2026-01-01', $done->fresh()->planned_date->toDateString());
    }
}
