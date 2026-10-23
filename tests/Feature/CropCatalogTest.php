<?php

namespace Tests\Feature;

use App\Enums\FarmRole;
use App\Models\Crop;
use App\Models\CropCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithFarm;
use Tests\TestCase;

class CropCatalogTest extends TestCase
{
    use InteractsWithFarm, RefreshDatabase;

    public function test_la_liste_des_cultures_affiche_et_filtre_les_categories(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $fruits = CropCategory::factory()->create(['name' => 'Légumes-fruits', 'position' => 1]);
        $feuilles = CropCategory::factory()->create(['name' => 'Légumes-feuilles', 'position' => 2]);

        Crop::factory()->create([
            'farm_id' => $farm->id,
            'category_id' => $fruits->id,
            'name' => 'Tomate',
            'variety' => 'Mercure',
        ]);

        Crop::factory()->create([
            'farm_id' => $farm->id,
            'category_id' => $feuilles->id,
            'name' => 'Laitue',
            'variety' => 'Batavia',
        ]);

        $this->get(route('crops.index'))
            ->assertOk()
            ->assertSee('Légumes-fruits')
            ->assertSee('Légumes-feuilles')
            ->assertSee('Tomate')
            ->assertSee('Laitue');

        $this->get(route('crops.index', ['category_id' => $feuilles->id]))
            ->assertOk()
            ->assertSee('Laitue')
            ->assertDontSee('Tomate');
    }

    public function test_une_culture_doit_avoir_une_categorie(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $category = CropCategory::factory()->create(['name' => 'Légumes-racines']);

        $this->post(route('crops.store'), [
            'name' => 'Carotte',
            'variety' => 'Nantaise',
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('crops', ['name' => 'Carotte']);

        $this->post(route('crops.store'), [
            'category_id' => $category->id,
            'name' => 'Carotte',
            'variety' => 'Nantaise',
            'estimated_cycle_days' => 95,
            'is_active' => '1',
        ])->assertRedirect(route('crops.index'));

        $this->assertDatabaseHas('crops', [
            'farm_id' => $farm->id,
            'category_id' => $category->id,
            'name' => 'Carotte',
            'variety' => 'Nantaise',
        ]);

        $crop = Crop::query()->where('farm_id', $farm->id)->where('name', 'Carotte')->firstOrFail();

        $this->assertDatabaseHas('crop_guides', ['crop_id' => $crop->id]);
    }

    public function test_la_fiche_de_culture_affiche_les_etapes_et_les_points_specifiques(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $category = CropCategory::factory()->create([
            'name' => 'Légumes-fruits',
            'slug' => 'legumes-fruits',
        ]);

        $crop = Crop::factory()->create([
            'farm_id' => $farm->id,
            'category_id' => $category->id,
            'name' => 'Tomate',
            'variety' => 'Mercure',
        ]);

        $this->get(route('crops.guide', $crop))
            ->assertOk()
            ->assertSee('Fiche de culture')
            ->assertSee('Étapes communes')
            ->assertSee('Étapes spécifiques — Légumes-fruits')
            ->assertSee('Points spécifiques — Tomate')
            ->assertSee('Pépinière')
            ->assertSee('Récolte');

        $this->assertDatabaseHas('crop_guides', ['crop_id' => $crop->id]);
    }

    public function test_la_fiche_est_resynchronisee_apres_un_changement_de_categorie(): void
    {
        $farm = $this->createFarm();
        $this->actingAsFarmMember($farm, FarmRole::Responsable);

        $feuilles = CropCategory::factory()->create([
            'name' => 'Légumes-feuilles',
            'slug' => 'legumes-feuilles',
        ]);
        $fruits = CropCategory::factory()->create([
            'name' => 'Légumes-fruits',
            'slug' => 'legumes-fruits',
        ]);

        $crop = Crop::factory()->create([
            'farm_id' => $farm->id,
            'category_id' => $feuilles->id,
            'name' => 'Laitue',
        ]);

        $this->get(route('crops.guide', $crop))->assertOk();

        $this->put(route('crops.update', $crop), [
            'category_id' => $fruits->id,
            'name' => 'Laitue',
            'variety' => 'non_precisee',
            'is_active' => '1',
        ])->assertRedirect(route('crops.index'));

        $guide = $crop->fresh()->guide;

        $this->assertNotNull($guide);
        $this->assertSame('Choisir la variété et la période', $guide->category_steps[0]['title']);
    }
}
