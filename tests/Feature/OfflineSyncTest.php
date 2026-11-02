<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\CampaignStatus;
use App\Enums\ReferenceType;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Harvest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function activityPayload(Campaign $campaign, string $reference = 'offline-activity-1'): array
    {
        return [
            'client_reference' => $reference,
            'campaign_id' => $campaign->id,
            'type' => ActivityType::Weeding->value,
            'title' => 'Désherbage saisi hors connexion',
            'planned_date' => now()->toDateString(),
            'status' => ActivityStatus::Planned->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function harvestPayload(Campaign $campaign, string $reference = 'offline-harvest-1'): array
    {
        return [
            'client_reference' => $reference,
            'campaign_id' => $campaign->id,
            'quantity' => 25.5,
            'harvested_at' => now()->toDateTimeString(),
        ];
    }

    public function test_une_activite_hors_ligne_est_synchronisee_une_seule_fois(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);

        $first = $this->postJson(route('offline.activities'), $this->activityPayload($campaign));
        $second = $this->postJson(route('offline.activities'), $this->activityPayload($campaign));

        $first->assertOk()->assertJsonPath('status', 'created');
        $second->assertOk()->assertJsonPath('status', 'existing');

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, Activity::query()->where('farm_id', $farm->id)->count());
    }

    public function test_une_recolte_hors_ligne_est_synchronisee_une_seule_fois(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'status' => CampaignStatus::Ongoing->value,
            'reference_type' => ReferenceType::Planting->value,
        ]);

        $first = $this->postJson(route('offline.harvests'), $this->harvestPayload($campaign));
        $second = $this->postJson(route('offline.harvests'), $this->harvestPayload($campaign));

        $first->assertOk()->assertJsonPath('status', 'created');
        $second->assertOk()->assertJsonPath('status', 'existing');

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, Harvest::query()->where('farm_id', $farm->id)->count());
        $this->assertDatabaseCount('produce_lots', 1);
    }

    public function test_la_reference_de_synchronisation_est_obligatoire(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);

        $payload = $this->activityPayload($campaign);
        unset($payload['client_reference']);

        $this->postJson(route('offline.activities'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('client_reference');
    }

    public function test_les_formulaires_exposent_la_file_de_synchronisation_hors_ligne(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $this->get(route('activities.create'))
            ->assertOk()
            ->assertSee('data-offline-form', false)
            ->assertSee(route('offline.activities'), false);

        $this->get(route('harvests.create'))
            ->assertOk()
            ->assertSee('data-offline-form', false)
            ->assertSee(route('offline.harvests'), false);
    }

    public function test_la_manifeste_et_la_banniere_hors_ligne_sont_presentes(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('id="offline-banner"', false)
            ->assertSee('name="user-id"', false);
    }

    public function test_la_meme_reference_reste_isolee_par_exploitation(): void
    {
        $firstFarm = $this->createFarm();
        $firstUser = $this->actingAsFarmMember($firstFarm);
        $firstCampaign = Campaign::factory()->create(['farm_id' => $firstFarm->id]);

        $this->postJson(route('offline.activities'), $this->activityPayload($firstCampaign))
            ->assertOk()
            ->assertJsonPath('status', 'created');

        $secondFarm = $this->createFarm();
        $this->actingAsFarmMember($secondFarm);
        $secondCampaign = Campaign::factory()->create(['farm_id' => $secondFarm->id]);

        $this->postJson(route('offline.activities'), $this->activityPayload($secondCampaign))
            ->assertOk()
            ->assertJsonPath('status', 'created');

        $this->assertSame(2, Activity::query()->count());
        $this->assertNotSame($firstUser->id, auth()->id());
    }
}
