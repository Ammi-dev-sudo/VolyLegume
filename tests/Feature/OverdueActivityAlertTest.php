<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class OverdueActivityAlertTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_une_activite_de_plus_de_trois_jours_declenche_une_alerte_et_une_consequence(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $activity = Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'type' => ActivityType::Irrigation->value,
            'status' => ActivityStatus::Planned->value,
            'planned_date' => now()->subDays(5)->toDateString(),
        ]);
        $this->actingAsFarmMember($farm);

        $this->get(route('activities.show', $activity))
            ->assertOk()
            ->assertSee('Alerte : activité en retard de 5 jours')
            ->assertSee('Conséquence')
            ->assertSee('stress hydrique');

        $activity->refresh();

        $this->assertNotNull($activity->overdue_notified_at);
        $this->assertNotNull($activity->overdue_consequence);
    }

    public function test_un_retard_de_moins_de_trois_jours_ne_declenche_pas_l_alerte_critique(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $activity = Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'status' => ActivityStatus::Planned->value,
            'planned_date' => now()->subDay()->toDateString(),
        ]);
        $this->actingAsFarmMember($farm);

        $this->get(route('activities.show', $activity))
            ->assertOk()
            ->assertSee('alerte critique se déclenche au-delà de 3 jours')
            ->assertDontSee('Alerte : activité en retard');

        $this->assertNull($activity->fresh()->overdue_notified_at);
    }

    public function test_le_filtre_critique_liste_uniquement_les_retards_superieurs_a_trois_jours(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Activité critique',
            'status' => ActivityStatus::Planned->value,
            'planned_date' => now()->subDays(6)->toDateString(),
        ]);
        Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'title' => 'Activité récente',
            'status' => ActivityStatus::Planned->value,
            'planned_date' => now()->subDay()->toDateString(),
        ]);
        $this->actingAsFarmMember($farm);

        $this->get(route('activities.index', ['critical' => 1]))
            ->assertOk()
            ->assertSee('Activité critique')
            ->assertDontSee('Activité récente');
    }
}
