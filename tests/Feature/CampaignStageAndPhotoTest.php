<?php

namespace Tests\Feature;

use App\Enums\CropStage;
use App\Enums\FarmRole;
use App\Models\Campaign;
use App\Models\CampaignStageEvent;
use App\Models\ProductionUnitPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class CampaignStageAndPhotoTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_une_etape_est_enregistree_et_la_progression_ne_depend_que_des_evenements(): void
    {
        Storage::fake('public');
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->post(route('campaigns.stages.store', $campaign), [
            'stage' => CropStage::Sowing->value,
            'recorded_at' => now()->toDateString(),
            'notes' => 'Semis réalisé à la main.',
            'photo' => UploadedFile::fake()->image('semis.jpg'),
            'caption' => 'Semis du matin',
        ])->assertRedirect(route('campaigns.show', $campaign));

        $event = CampaignStageEvent::firstOrFail();
        $this->assertSame(CropStage::Sowing, $event->stage);
        $this->assertSame(1, CampaignStageEvent::count());
        $this->assertSame(1, ProductionUnitPhoto::count());

        $this->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Cycle cultural')
            ->assertSee('11 %')
            ->assertSee('Semis');
    }

    public function test_re_enregistrer_une_etape_met_a_jour_l_evenement_sans_doublon(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $this->actingAsFarmMember($farm);

        $this->post(route('campaigns.stages.store', $campaign), [
            'stage' => CropStage::Growth->value,
            'recorded_at' => now()->subDays(4)->toDateString(),
            'notes' => 'Première observation.',
        ])->assertRedirect();

        $this->post(route('campaigns.stages.store', $campaign), [
            'stage' => CropStage::Growth->value,
            'recorded_at' => now()->subDay()->toDateString(),
            'notes' => 'Observation confirmée.',
        ])->assertRedirect();

        $this->assertSame(1, CampaignStageEvent::count());
        $this->assertSame('Observation confirmée.', CampaignStageEvent::firstOrFail()->notes);
    }

    public function test_la_galerie_et_la_comparaison_de_photos_sont_accessibles(): void
    {
        Storage::fake('public');
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $this->actingAsFarmMember($farm);

        $this->post(route('campaigns.photos.store', $campaign), [
            'photo' => UploadedFile::fake()->image('avant.jpg'),
            'growth_stage' => CropStage::Growth->value,
            'observation_type' => 'disease',
            'taken_at' => now()->subDays(10)->toDateString(),
            'caption' => 'Avant traitement',
        ])->assertRedirect();

        $this->post(route('campaigns.photos.store', $campaign), [
            'photo' => UploadedFile::fake()->image('apres.jpg'),
            'growth_stage' => CropStage::Flowering->value,
            'observation_type' => 'after',
            'taken_at' => now()->toDateString(),
            'caption' => 'Après traitement',
        ])->assertRedirect();

        $photos = ProductionUnitPhoto::orderBy('taken_at')->get();
        $this->assertCount(2, $photos);

        $this->get(route('campaigns.photos.index', $campaign))
            ->assertOk()
            ->assertSee('Suivi photographique')
            ->assertSee('Avant traitement')
            ->assertSee('Après traitement');

        $this->get(route('campaigns.photos.compare', [
            'campaign' => $campaign,
            'before' => $photos->first()->id,
            'after' => $photos->last()->id,
        ]))->assertOk()
            ->assertSee('Avant / après')
            ->assertSee('10 jour(s)');
    }

    public function test_un_agent_ne_peut_pas_supprimer_une_etape(): void
    {
        $farm = $this->createFarm();
        $campaign = Campaign::factory()->create(['farm_id' => $farm->id]);
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->post(route('campaigns.stages.store', $campaign), [
            'stage' => CropStage::Germination->value,
            'recorded_at' => now()->toDateString(),
        ]);

        $event = CampaignStageEvent::firstOrFail();

        $this->delete(route('campaigns.stages.destroy', $event))->assertForbidden();
        $this->assertDatabaseHas('campaign_stage_events', ['id' => $event->id]);
    }
}
