<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Enums\RevenueCategory;
use App\Models\Campaign;
use App\Models\Harvest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class FinanceAuthorizationTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_agent_ne_peut_pas_creer_de_depense(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('expenses.create'))->assertForbidden();
        $this->post(route('expenses.store'), [
            'category' => 'labour',
            'description' => 'Main-d’œuvre',
            'amount' => 1000,
            'expense_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_un_agent_ne_peut_pas_consulter_les_pages_financieres(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('expenses.index'))->assertForbidden();
        $this->get(route('revenues.index'))->assertForbidden();
        $this->get(route('profitability.index'))->assertForbidden();
        $this->get(route('profitability.print'))->assertForbidden();
        $this->get(route('exports.finances'))->assertForbidden();
    }

    public function test_un_responsable_peut_consulter_les_pages_financieres(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $this->get(route('expenses.index'))->assertOk();
        $this->get(route('revenues.index'))->assertOk();
        $this->get(route('profitability.index'))->assertOk();
    }

    public function test_le_tableau_de_bord_masque_les_montants_aux_agents(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Bénéfice simplifié')
            ->assertDontSee('Voir la rentabilité')
            ->assertSee('Votre rôle ne donne pas accès aux montants financiers.');
    }

    public function test_le_tableau_de_bord_affiche_les_montants_aux_responsables(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bénéfice simplifié')
            ->assertSee('Voir la rentabilité');
    }

    public function test_un_responsable_peut_creer_une_depense(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $this->post(route('expenses.store'), [
            'category' => 'labour',
            'description' => 'Main-d’œuvre',
            'amount' => 15000,
            'expense_date' => now()->toDateString(),
        ])->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'farm_id' => $farm->id,
            'description' => 'Main-d’œuvre',
        ]);
    }

    public function test_une_recolte_etrniere_ne_peut_pas_etre_liee_a_une_autre_campagne(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $otherCampaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $harvest = Harvest::factory()->create(['farm_id' => $farm->id, 'campaign_id' => $otherCampaign->id]);

        $this->from(route('revenues.create'))->post(route('revenues.store'), [
            'campaign_id' => $campaign->id,
            'harvest_id' => $harvest->id,
            'category' => RevenueCategory::HarvestSale->value,
            'description' => 'Vente',
            'amount' => 50000,
            'revenue_date' => now()->toDateString(),
        ])->assertSessionHasErrors('harvest_id');
    }

    public function test_une_recette_liee_a_une_recolte_renseigne_la_campagne(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $harvest = Harvest::factory()->create(['farm_id' => $farm->id, 'campaign_id' => $campaign->id]);

        $this->post(route('revenues.store'), [
            'harvest_id' => $harvest->id,
            'category' => RevenueCategory::HarvestSale->value,
            'description' => 'Vente récolte',
            'amount' => 80000,
            'revenue_date' => now()->toDateString(),
        ])->assertRedirect(route('revenues.index'));

        $this->assertDatabaseHas('revenues', [
            'harvest_id' => $harvest->id,
            'campaign_id' => $campaign->id,
        ]);
    }
}
