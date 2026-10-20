<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ApiActivitiesTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_sans_token_la_creation_repond_401(): void
    {
        $this->postJson('/api/activities', [])->assertUnauthorized();
    }

    public function test_un_membre_autorise_peut_creer_une_activite(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $user = $this->memberOf($farm);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/activities', [
                'campaign_id' => $campaign->id,
                'type' => ActivityType::Weeding->value,
                'title' => 'Désherbage rangées',
                'planned_date' => now()->addDay()->toDateString(),
                'status' => ActivityStatus::Planned->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.farm_id', $farm->id)
            ->assertJsonPath('data.campaign_id', $campaign->id)
            ->assertJsonPath('data.created_by', $user->id)
            ->assertJsonPath('data.title', 'Désherbage rangées');

        $this->assertDatabaseHas('activities', [
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Désherbage rangées',
        ]);
    }

    public function test_un_payload_incomplet_repond_422(): void
    {
        $farm = $this->createFarm();
        $user = $this->memberOf($farm);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/activities', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['campaign_id', 'type', 'title', 'planned_date', 'status']);

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_une_campagne_etrangere_est_refusee(): void
    {
        $farm = $this->createFarm();
        $foreignCampaign = Campaign::factory()->create();
        $user = $this->memberOf($farm);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/activities', [
                'campaign_id' => $foreignCampaign->id,
                'type' => ActivityType::Sowing->value,
                'title' => 'Semis',
                'planned_date' => now()->toDateString(),
                'status' => ActivityStatus::Planned->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['campaign_id']);

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_une_activite_realisee_renseigne_la_realisation_cote_serveur(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $user = $this->memberOf($farm);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/activities', [
                'campaign_id' => $campaign->id,
                'type' => ActivityType::Transplanting->value,
                'title' => 'Repiquage',
                'planned_date' => now()->toDateString(),
                'status' => ActivityStatus::Completed->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ActivityStatus::Completed->value)
            ->assertJsonPath('data.performed_by', $user->id);

        $this->assertDatabaseHas('activities', [
            'campaign_id' => $campaign->id,
            'performed_by' => $user->id,
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('tests')->plainTextToken;
    }
}
