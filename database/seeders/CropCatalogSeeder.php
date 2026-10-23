<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Models\CropCategory;
use App\Models\Farm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue initial des catégories et des légumes.
 *
 * Les noms sont dédupliqués : lorsqu'un légume apparaît dans plusieurs
 * catégories, sa première catégorie sert de classement principal, sauf
 * correction explicite pour quelques usages (herbes aromatiques notamment).
 */
class CropCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $categories = [];

            foreach ($this->catalog() as $position => $definition) {
                $categories[$definition['slug']] = CropCategory::updateOrCreate(
                    ['slug' => $definition['slug']],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'position' => $position + 1,
                        'is_active' => true,
                    ],
                );
            }

            $seen = [];

            foreach ($this->catalog() as $definition) {
                $category = $categories[$definition['slug']];

                foreach ($definition['vegetables'] as $name) {
                    $key = $this->normalize($name);

                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;

                    $this->seedVegetable($name, $category);
                }
            }

            $this->assignRemainingCategories($categories);
            $this->applyCategoryOverrides($categories);
        });
    }

    private function seedVegetable(string $name, CropCategory $category): void
    {
        foreach (Farm::query()->orderBy('id')->get() as $farm) {
            $query = Crop::query()
                ->where('farm_id', $farm->id)
                ->where('name', $name);

            if ($query->exists()) {
                $query->update(['category_id' => $category->id]);

                continue;
            }

            Crop::create([
                'farm_id' => $farm->id,
                'category_id' => $category->id,
                'name' => $name,
                'variety' => 'non_precisee',
                'production_unit' => 'kg',
                'estimated_cycle_days' => null,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array<string, CropCategory>  $categories
     */
    private function assignRemainingCategories(array $categories): void
    {
        $fallbacks = [
            'Tomate' => 'legumes-fruits',
            'Carotte' => 'legumes-racines',
            'Laitue' => 'legumes-feuilles',
            'Oignon' => 'legumes-a-bulbes',
            'Poivron' => 'legumes-fruits',
            'Concombre' => 'legumes-fruits',
            'Aubergine' => 'legumes-fruits',
            'Courgette' => 'legumes-fruits',
            'Chou' => 'legumes-feuilles',
        ];

        Crop::query()
            ->whereNull('category_id')
            ->get()
            ->each(function (Crop $crop) use ($categories, $fallbacks): void {
                $slug = $fallbacks[$crop->name] ?? 'autres';

                if (isset($categories[$slug])) {
                    $crop->update(['category_id' => $categories[$slug]->id]);
                }
            });
    }

    /**
     * Corrige les classements où la première catégorie n’est pas la plus
     * pertinente pour l’usage principal du légume.
     *
     * @param  array<string, CropCategory>  $categories
     */
    private function applyCategoryOverrides(array $categories): void
    {
        $overrides = [
            'Persil' => 'legumes-aromatiques',
            'Coriandre' => 'legumes-aromatiques',
            'Ciboulette' => 'legumes-aromatiques',
        ];

        Crop::query()
            ->whereIn('name', array_keys($overrides))
            ->get()
            ->each(function (Crop $crop) use ($categories, $overrides): void {
                $slug = $overrides[$crop->name];

                if (isset($categories[$slug])) {
                    $crop->update(['category_id' => $categories[$slug]->id]);
                }
            });
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
    }

    /**
     * @return list<array{name: string, slug: string, description: string, vegetables: list<string>}>
     */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Légumes-feuilles',
                'slug' => 'legumes-feuilles',
                'description' => 'Légumes dont on consomme principalement les feuilles.',
                'vegetables' => [
                    'Laitue',
                    'Épinard',
                    'Chou chinois',
                    'Chou kale',
                    'Chou cavalier',
                    'Blette',
                    'Roquette',
                    'Mâche',
                    'Endive',
                    'Chicorée',
                    'Cresson',
                    'Persil',
                    'Coriandre',
                    'Feuilles de moutarde',
                    'Pak-choï',
                    'Pé-tsaï',
                    'Amarante (anamamy)',
                    'Brèdes mafana',
                    'Brèdes morelles',
                    'Brèdes de patate douce',
                    'Brèdes de manioc',
                    'Feuilles de taro',
                    'Feuilles de citrouille',
                    'Feuilles de niébé',
                    'Feuilles de patole',
                    'Anatsonga',
                    'Petsay',
                    'Oseille',
                    'Feuilles de fenugrec',
                    'Feuilles de navet',
                    'Chou vert',
                    'Chou rouge',
                    'Chou blanc',
                    'Chou frisé',
                    'Chou de Milan',
                    'Chou de Bruxelles',
                    'Chou palmier',
                    'Chou portugais',
                ],
            ],
            [
                'name' => 'Légumes-fruits',
                'slug' => 'legumes-fruits',
                'description' => 'Légumes dont on consomme principalement le fruit botanique.',
                'vegetables' => [
                    'Tomate',
                    'Tomate cerise',
                    'Aubergine',
                    'Aubergine africaine',
                    'Poivron vert',
                    'Poivron rouge',
                    'Poivron jaune',
                    'Piment rouge',
                    'Piment vert',
                    'Piment doux',
                    'Concombre',
                    'Cornichon',
                    'Courgette',
                    'Courge',
                    'Citrouille',
                    'Potimarron',
                    'Butternut',
                    'Gombo',
                    'Chayote (christophine)',
                    'Patole',
                    'Luffa comestible',
                    'Melon amer',
                    'Pastèque consommée comme légume dans certaines cuisines',
                    'Fruit du jacquier consommé vert',
                    'Fruit à pain consommé vert',
                    "Pois d'Angole frais",
                    'Haricot kilomètre',
                    'Dolique asperge',
                    'Tomatille',
                    'Physalis comestible',
                ],
            ],
            [
                'name' => 'Légumes-racines',
                'slug' => 'legumes-racines',
                'description' => 'Légumes dont on consomme principalement la racine.',
                'vegetables' => [
                    'Carotte',
                    'Radis rouge',
                    'Radis blanc (daikon)',
                    'Navet',
                    'Betterave rouge',
                    'Betterave jaune',
                    'Panais',
                    'Céleri-rave',
                    'Salsifis',
                    'Scorsonère',
                    'Raifort',
                    'Rutabaga',
                    'Persil tubéreux',
                    'Cerfeuil tubéreux',
                    'Racine de lotus',
                    'Racine de bardane',
                    'Racine de chicorée',
                    'Racine de pissenlit',
                    'Manioc',
                    'Macabo',
                ],
            ],
            [
                'name' => 'Légumes-tubercules',
                'slug' => 'legumes-tubercules',
                'description' => 'Légumes dont on consomme les tubercules.',
                'vegetables' => [
                    'Pomme de terre',
                    'Patate douce',
                    'Igname',
                    'Taro',
                    'Oca du Pérou',
                    'Topinambour',
                    'Yacon',
                    'Crosne du Japon',
                    'Ulluco',
                    'Pomme de terre violette',
                ],
            ],
            [
                'name' => 'Légumes à bulbes',
                'slug' => 'legumes-a-bulbes',
                'description' => 'Légumes dont on consomme principalement le bulbe.',
                'vegetables' => [
                    'Oignon blanc',
                    'Oignon rouge',
                    'Oignon jaune',
                    'Ail',
                    'Échalote',
                    'Poireau',
                    'Oignon vert',
                    'Ciboule',
                    'Ciboulette',
                    'Fenouil bulbeux',
                    'Ail des ours',
                    'Oignon perlé',
                ],
            ],
            [
                'name' => 'Légumes à tiges',
                'slug' => 'legumes-a-tiges',
                'description' => 'Légumes dont on consomme la tige, le pétiole ou la pousse.',
                'vegetables' => [
                    'Asperge verte',
                    'Asperge blanche',
                    'Céleri-branche',
                    'Rhubarbe',
                    'Cardon',
                    'Bambou comestible',
                    'Pousses de bambou',
                    'Cœur de palmier',
                    'Chou-rave',
                    'Pousses de fougère comestibles',
                ],
            ],
            [
                'name' => 'Légumes-fleurs',
                'slug' => 'legumes-fleurs',
                'description' => 'Légumes dont on consomme les fleurs ou les inflorescences.',
                'vegetables' => [
                    'Chou-fleur blanc',
                    'Chou-fleur violet',
                    'Brocoli',
                    'Brocoli chinois',
                    'Romanesco',
                    'Artichaut',
                    'Fleur de bananier',
                    'Fleurs de courge comestibles',
                    'Brocoli-rave',
                    'Chou-fleur vert',
                    'Chou-fleur',
                ],
            ],
            [
                'name' => 'Légumes à gousses et graines',
                'slug' => 'legumes-a-gousses-et-graines',
                'description' => 'Légumes dont on consomme les gousses ou les graines fraîches.',
                'vegetables' => [
                    'Haricot vert',
                    'Haricot beurre',
                    'Haricot plat',
                    'Haricot kilomètre',
                    'Petit pois',
                    'Pois mange-tout',
                    'Pois gourmand',
                    'Fève fraîche',
                    'Pois chiche frais',
                    'Lentille fraîche',
                    "Pois d'Angole",
                    'Niébé',
                    'Haricot mungo',
                    'Haricot rouge frais',
                    'Haricot blanc frais',
                    'Soja vert (edamame)',
                    'Pois bambara',
                    'Pois carré',
                    'Lupin comestible',
                    'Maïs doux',
                ],
            ],
            [
                'name' => 'Légumes aromatiques',
                'slug' => 'legumes-aromatiques',
                'description' => 'Plantes aromatiques et condimentaires cultivées au potager.',
                'vegetables' => [
                    'Persil',
                    'Coriandre',
                    'Ciboulette',
                    'Basilic',
                    'Menthe',
                    'Thym',
                    'Romarin',
                    'Origan',
                    'Aneth',
                    'Estragon',
                    'Sauge',
                    'Laurier',
                    'Cerfeuil',
                    'Fenouil aromatique',
                    'Gingembre',
                    'Curcuma',
                    'Galanga',
                    'Raifort',
                    'Wasabi',
                    'Citronnelle',
                ],
            ],
            [
                'name' => 'Champignons',
                'slug' => 'champignons',
                'description' => 'Champignons comestibles, classés séparément des légumes.',
                'vegetables' => [
                    'Champignon de Paris',
                    'Pleurote',
                    'Shiitaké',
                    'Enoki',
                    'Shimeji',
                    'Morille',
                    'Girolle',
                    'Cèpe',
                    'Champignon de paille',
                    "Champignon d'huître royal",
                ],
            ],
            [
                'name' => 'Légumes tropicaux',
                'slug' => 'legumes-tropicaux',
                'description' => 'Espèces tropicales et légumes moins courants adaptés au contexte malgache.',
                'vegetables' => [
                    'Brèdes mafana',
                    'Anamamy',
                    'Anatsonga',
                    'Petsay',
                    'Gombo',
                    'Aubergine africaine',
                    'Amarante potagère',
                    'Niébé',
                    "Pois d'Angole",
                    'Chayote',
                    'Patole',
                    'Luffa comestible',
                    'Manioc',
                    'Patate douce',
                    'Taro',
                    'Igname',
                    'Macabo',
                    'Feuilles de manioc',
                    'Feuilles de patate douce',
                    'Feuilles de taro',
                    'Feuilles de citrouille',
                    'Feuilles de niébé',
                    'Morelle noire comestible',
                    'Corète potagère',
                    'Épinard de Malabar',
                    'Pourpier',
                    'Moringa, feuilles consommées comme légume',
                    'Bambou comestible',
                    'Aubergine longue asiatique',
                    'Concombre amer',
                ],
            ],
            [
                'name' => 'Autres',
                'slug' => 'autres',
                'description' => 'Cultures non classées dans les catégories principales.',
                'vegetables' => [],
            ],
        ];
    }
}
