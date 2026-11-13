<?php

namespace Tests\Feature;

use App\Enums\InputCategory;
use App\Models\Campaign;
use App\Models\Input;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class DashboardCacheTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le magasin `array` ne sérialise pas ses valeurs : le bug ne se voit
        // qu'avec un magasin qui sérialise, comme `database` en production.
        config(['cache.default' => 'database']);
        Cache::purge();
    }

    public function test_le_tableau_de_bord_reste_disponible_apres_mise_en_cache(): void
    {
        $farm = $this->createFarm();

        Input::factory()->create([
            'farm_id' => $farm->id,
            'name' => 'Fongicide cuivre',
            'category' => InputCategory::Phytosanitary,
            'quantity' => 3,
            'minimum_stock' => 5,
        ]);

        $this->actingAsFarmMember($farm);

        $this->get(route('dashboard'))->assertOk()->assertSee('Fongicide cuivre');

        // Second appel : les agrégats proviennent du cache, ils ne doivent pas
        // être désérialisés en objets incomplets.
        $this->get(route('dashboard'))->assertOk()->assertSee('Fongicide cuivre');
    }

    public function test_une_activite_creee_invalide_le_cache_du_tableau_de_bord(): void
    {
        $farm = $this->createFarm();
        $user = $this->actingAsFarmMember($farm);

        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);

        $this->get(route('dashboard'))->assertOk()->assertDontSee('Désherbage tardif');

        $this->post(route('activities.store'), [
            'campaign_id' => $campaign->id,
            'type' => 'weeding',
            'title' => 'Désherbage tardif',
            'planned_date' => now()->toDateString(),
            'status' => 'planned',
            'assigned_to' => $user->id,
        ])->assertRedirect();

        $this->get(route('dashboard'))->assertOk()->assertSee('Désherbage tardif');
    }
}
