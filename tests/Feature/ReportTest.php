<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Enums\IssueSeverity;
use App\Models\Campaign;
use App\Models\CropIssue;
use App\Models\Expense;
use App\Models\ProductionUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_responsable_consulte_un_rapport_avec_synthese_et_exports(): void
    {
        Storage::fake('public');
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'name' => 'Tomate rapport',
        ]);
        $user = $this->actingAsFarmMember($farm);

        CropIssue::factory()->forCampaign($campaign)->create([
            'reported_by' => $user->id,
            'title' => 'Mildiou de la tomate',
            'severity' => IssueSeverity::Critical->value,
        ]);

        Expense::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'amount' => 25000,
            'expense_date' => now()->toDateString(),
            'recorded_by' => $user->id,
        ]);

        $query = ['from' => now()->startOfYear()->toDateString(), 'to' => now()->toDateString()];

        $this->get(route('reports.index', $query))
            ->assertOk()
            ->assertSee('Rapports d’exploitation')
            ->assertSee('Tomate rapport')
            ->assertSee('Mildiou de la tomate')
            ->assertSee('Marge simplifiée');

        $excel = $this->get(route('reports.excel', $query));
        $excel->assertOk();
        $excel->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $excel->streamedContent());

        $pdf = $this->get(route('reports.pdf', $query));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_un_agent_obtient_un_rapport_sans_montants_financiers(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $user = $this->actingAsFarmMember($farm, FarmRole::Agent);

        Expense::factory()->create([
            'farm_id' => $farm->id,
            'campaign_id' => $campaign->id,
            'amount' => 50000,
            'expense_date' => now()->toDateString(),
            'recorded_by' => $user->id,
        ]);

        $query = ['from' => now()->startOfYear()->toDateString(), 'to' => now()->toDateString()];

        $this->get(route('reports.index', $query))
            ->assertOk()
            ->assertDontSee('Marge simplifiée')
            ->assertDontSee('Journal financier');

        $this->get(route('reports.excel', $query))->assertOk();
        $this->get(route('reports.pdf', $query))->assertOk();
    }

    public function test_une_campagne_d_une_autre_exploitation_est_refusee_comme_filtre(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $foreignCampaign = Campaign::factory()->create();

        $this->get(route('reports.index', ['campaign_id' => $foreignCampaign->id]))
            ->assertSessionHasErrors('campaign_id');
    }
}
