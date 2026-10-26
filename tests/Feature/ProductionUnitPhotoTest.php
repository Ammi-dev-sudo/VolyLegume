<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Models\Campaign;
use App\Models\ProductionUnit;
use App\Models\ProductionUnitPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class ProductionUnitPhotoTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_une_photo_peut_etre_ajoutee_a_une_parcelle(): void
    {
        Storage::fake('public');
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $campaign = Campaign::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
        ]);
        $this->actingAsFarmMember($farm);

        $this->post(route('production-units.photos.store', $unit), [
            'photo' => UploadedFile::fake()->image('parcelle.jpg'),
            'caption' => 'Après repiquage',
            'taken_at' => now()->toDateString(),
            'campaign_id' => $campaign->id,
        ])->assertRedirect();

        $photo = ProductionUnitPhoto::firstOrFail();
        $this->assertSame($farm->id, $photo->farm_id);
        $this->assertSame('Après repiquage', $photo->caption);
        Storage::disk('public')->assertExists($photo->photo_path);
        $this->assertDatabaseHas('production_unit_photos', ['farm_id' => $farm->id]);
    }

    public function test_la_liste_des_parcelles_s_affiche_avec_ou_sans_photo(): void
    {
        $farm = $this->createFarm();
        ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-PHOTO',
            'photo_path' => 'production-units/photo.jpg',
        ]);
        ProductionUnit::factory()->create([
            'farm_id' => $farm->id,
            'code' => 'P-SANS',
        ]);
        $this->actingAsFarmMember($farm);

        $this->get(route('production-units.index'))
            ->assertOk()
            ->assertSee('P-PHOTO')
            ->assertSee('P-SANS')
            ->assertSee('Oui');
    }

    public function test_une_campagne_d_une_autre_exploitation_est_refusee(): void
    {
        Storage::fake('public');
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $otherFarm = $this->createFarm();
        $foreignCampaign = Campaign::factory()->create(['farm_id' => $otherFarm->id]);
        $this->actingAsFarmMember($farm);

        $this->from(route('farm-map.index'))
            ->post(route('production-units.photos.store', $unit), [
                'photo' => UploadedFile::fake()->image('parcelle.jpg'),
                'campaign_id' => $foreignCampaign->id,
            ])
            ->assertSessionHasErrors('campaign_id');

        $this->assertSame(0, ProductionUnitPhoto::count());
    }

    public function test_un_agent_ne_peut_pas_supprimer_une_photo(): void
    {
        $farm = $this->createFarm();
        $unit = ProductionUnit::factory()->create(['farm_id' => $farm->id]);
        $photo = ProductionUnitPhoto::factory()->create([
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'photo_path' => 'production-unit-photos/test.jpg',
        ]);
        $this->actingAsFarmMember($farm, FarmRole::Agent);

        $this->delete(route('production-units.photos.destroy', $photo))->assertForbidden();
        $this->assertDatabaseHas('production_unit_photos', ['id' => $photo->id]);
    }
}
