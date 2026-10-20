<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ApiCampaignsTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_sans_token_la_liste_repond_401(): void
    {
        $this->getJson('/api/campaigns')->assertUnauthorized();
    }

    public function test_un_membre_liste_les_campagnes_de_son_exploitation(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        Campaign::factory()->create();

        $token = $this->tokenFor($this->memberOf($farm));

        $this->withToken($token)
            ->getJson('/api/campaigns')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $campaign->id)
            ->assertJsonPath('data.0.status', $campaign->status->value);
    }

    public function test_une_campagne_etrangere_repond_404(): void
    {
        $farm = $this->createFarm();
        $foreignCampaign = Campaign::factory()->create();

        $token = $this->tokenFor($this->memberOf($farm));

        $this->withToken($token)
            ->getJson("/api/campaigns/{$foreignCampaign->id}")
            ->assertNotFound();
    }

    public function test_l_entete_x_farm_id_selectionne_lexploitation_autorisee(): void
    {
        $farmA = $this->createFarm();
        $farmB = $this->createFarm();

        $user = $this->memberOf($farmA);
        $this->memberOf($farmB, FarmRole::Agent);

        $campaignB = Campaign::factory()->create(['farm_id' => $farmB->id]);

        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->withHeader('X-Farm-Id', (string) $farmB->id)
            ->getJson('/api/campaigns')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $campaignB->id);
    }

    public function test_l_entete_x_farm_id_non_rattachee_repond_404(): void
    {
        $farm = $this->createFarm();
        $foreignFarm = $this->createFarm();

        $token = $this->tokenFor($this->memberOf($farm));

        $this->withToken($token)
            ->withHeader('X-Farm-Id', (string) $foreignFarm->id)
            ->getJson('/api/campaigns')
            ->assertNotFound();
    }

    public function test_un_compte_sans_exploitation_repond_403(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->getJson('/api/campaigns')
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('tests')->plainTextToken;
    }
}
