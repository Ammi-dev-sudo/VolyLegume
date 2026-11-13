<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Enums\IssuePhotoKind;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Campaign;
use App\Models\CropIssue;
use App\Models\CropIssuePhoto;
use App\Models\ProductionUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class CropIssueTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_un_membre_cree_un_probleme_avec_reference_et_photo(): void
    {
        Storage::fake('public');
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
        ]);
        $user = $this->actingAsFarmMember($farm);

        $this->post(route('issues.store'), [
            'production_unit_id' => $unit->id,
            'campaign_id' => $campaign->id,
            'type' => IssueType::Disease->value,
            'title' => 'Mildiou sur feuilles basses',
            'description' => 'Taches brunes observées après la pluie.',
            'observed_at' => now()->toDateString(),
            'severity' => IssueSeverity::High->value,
            'status' => IssueStatus::Open->value,
            'affected_area_m2' => 12,
            'photo' => UploadedFile::fake()->image('mildiou.jpg'),
        ])->assertRedirect();

        $issue = CropIssue::firstOrFail();

        $this->assertSame($farm->id, $issue->farm_id);
        $this->assertSame($user->id, $issue->reported_by);
        $this->assertMatchesRegularExpression('/^PB-\d{8}-\d{3}$/', $issue->reference);
        $this->assertSame(IssueSeverity::High, $issue->severity);

        $photo = CropIssuePhoto::firstOrFail();
        $this->assertSame(IssuePhotoKind::Observation, $photo->kind);
        Storage::disk('public')->assertExists($photo->photo_path);

        $this->get(route('issues.show', $issue))
            ->assertOk()
            ->assertSee('Mildiou sur feuilles basses')
            ->assertSee($issue->reference);
    }

    public function test_un_agent_peut_creer_et_suivre_un_probleme_mais_pas_le_supprimer(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->post(route('issues.store'), [
            'production_unit_id' => $unit->id,
            'type' => IssueType::Pest->value,
            'title' => 'Chenilles sur jeunes plants',
            'description' => 'Feuilles perforées en bordure de parcelle.',
            'observed_at' => now()->toDateString(),
            'severity' => IssueSeverity::Moderate->value,
            'status' => IssueStatus::Open->value,
        ])->assertRedirect();

        $issue = CropIssue::firstOrFail();

        $this->put(route('issues.update', $issue), [
            'production_unit_id' => $unit->id,
            'type' => IssueType::Pest->value,
            'title' => 'Chenilles sur jeunes plants',
            'description' => 'Traitement biologique appliqué.',
            'observed_at' => now()->toDateString(),
            'severity' => IssueSeverity::Moderate->value,
            'status' => IssueStatus::InProgress->value,
        ])->assertRedirect();

        $this->delete(route('issues.destroy', $issue))->assertForbidden();
        $this->assertDatabaseHas('crop_issues', ['id' => $issue->id]);
    }

    public function test_le_statut_se_met_a_jour_sans_renvoyer_tout_le_formulaire(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $issue = CropIssue::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'status' => IssueStatus::Open->value,
        ]);
        $this->actingAsFarmMember($farm);

        $this->patch(route('issues.status', $issue), [
            'status' => IssueStatus::Resolved->value,
            'resolution_notes' => 'Symptômes disparus après traitement.',
        ])->assertRedirect(route('issues.show', $issue));

        $issue->refresh();
        $this->assertSame(IssueStatus::Resolved, $issue->status);
        $this->assertNotNull($issue->resolved_at);
        $this->assertSame('Symptômes disparus après traitement.', $issue->resolution_notes);
    }

    public function test_un_probleme_d_une_autre_exploitation_est_introuvable(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm);

        $foreignIssue = CropIssue::factory()->create();

        $this->get(route('issues.show', $foreignIssue))->assertNotFound();
        $this->get(route('issues.index'))->assertOk()->assertDontSee($foreignIssue->reference);
    }

    public function test_une_campagne_avec_des_problemes_ne_peut_pas_etre_supprimee(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $this->actingAsFarmMember($farm);

        CropIssue::factory()->forCampaign($campaign)->create();

        $this->from(route('campaigns.show', $campaign))
            ->delete(route('campaigns.destroy', $campaign))
            ->assertRedirect(route('campaigns.show', $campaign))
            ->assertSessionHasErrors('campaign');

        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }

    public function test_la_surface_affectee_ne_peut_pas_depasser_la_parcelle(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id, 'area_m2' => 100]);
        $this->actingAsFarmMember($farm);

        $this->post(route('issues.store'), [
            'production_unit_id' => $unit->id,
            'type' => IssueType::Drought->value,
            'title' => 'Sécheresse localisée',
            'description' => 'Sol desséché sur une grande partie de la parcelle.',
            'observed_at' => now()->toDateString(),
            'severity' => IssueSeverity::High->value,
            'status' => IssueStatus::Open->value,
            'affected_area_m2' => 150,
        ])->assertSessionHasErrors('affected_area_m2');

        $this->assertSame(0, CropIssue::count());
    }
}
