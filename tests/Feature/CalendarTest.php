<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Models\Activity;
use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_l_agenda_affiche_les_activites_du_mois(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'expected_end_date' => now()->endOfMonth()->toDateString(),
        ]);

        Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'planned_date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'title' => 'Désherbage de la parcelle nord',
        ]);

        $this->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Agenda de')
            ->assertSee('Désherbage de la parcelle nord')
            ->assertSee('Activités planifiées');
    }

    public function test_le_filtre_par_categorie_masque_les_autres_evenements(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'expected_end_date' => now()->endOfMonth()->toDateString(),
        ]);

        Activity::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'planned_date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'title' => 'Semis de tomates',
        ]);

        $this->get(route('calendar.index', ['show' => 'harvests']))
            ->assertOk()
            ->assertDontSee('Semis de tomates');
    }
}
