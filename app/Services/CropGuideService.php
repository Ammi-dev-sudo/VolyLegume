<?php

namespace App\Services;

use App\Models\Crop;
use App\Models\CropGuide;

/**
 * Construit et synchronise la fiche de culture d’un légume.
 *
 * Le contenu compile les étapes communes, les étapes de sa catégorie et les
 * points spécifiques connus. Les valeurs restent volontairement qualitatives :
 * durées, doses, espacements et rendements doivent être adaptés à la variété,
 * à la région et aux recommandations locales.
 */
class CropGuideService
{
    public function ensure(Crop $crop): CropGuide
    {
        $crop->loadMissing('guide');

        return $crop->guide ?? $this->sync($crop);
    }

    public function sync(Crop $crop): CropGuide
    {
        $crop->loadMissing('category');

        $slug = $crop->category?->slug ?? 'autres';
        $category = $this->categoryProfiles()[$slug] ?? $this->categoryProfiles()['autres'];
        $specific = $this->specificProfileFor($crop->name);

        $fields = array_replace($category['fields'], $specific['fields'] ?? []);

        if ($crop->variety !== null && $crop->variety !== 'non_precisee') {
            $fields['varieties'] = $crop->variety.' — '.$fields['varieties'];
        }

        if ($crop->estimated_cycle_days !== null) {
            $fields['cycle_duration'] = 'Cycle renseigné dans VolyLegume : '.$crop->estimated_cycle_days
                .' jours. '.$fields['cycle_duration'];
        }

        return CropGuide::updateOrCreate(
            ['crop_id' => $crop->id],
            $fields + [
                'common_steps' => $this->commonSteps(),
                'category_steps' => $category['steps'],
                'specific_steps' => $specific['steps'] ?? [],
            ],
        );
    }

    /**
     * @return list<array{title: string, items: list<string>}>
     */
    public function commonSteps(): array
    {
        return [
            [
                'title' => 'Choisir le légume et la variété',
                'items' => [
                    'Définir le légume, la variété et le débouché commercial.',
                    'Vérifier la saison, la durée estimée du cycle, les besoins en eau et en lumière.',
                    'Contrôler l’adaptation de la variété au climat local : Hautes Terres, littoral, saison des pluies ou saison sèche.',
                ],
            ],
            [
                'title' => 'Choisir et examiner la parcelle',
                'items' => [
                    'Choisir une parcelle suffisamment ensoleillée, avec un accès à une eau propre.',
                    'Observer le drainage après les pluies et vérifier la texture et la profondeur du sol.',
                    'Examiner les cultures précédentes, les maladies, les ravageurs et les risques de contamination.',
                    'Faire analyser le sol, notamment le pH et la fertilité, lorsque c’est possible.',
                ],
            ],
            [
                'title' => 'Préparer le terrain',
                'items' => [
                    'Retirer les déchets, les plastiques, les mauvaises herbes et les résidus de plantes malades.',
                    'Ameublir le sol sans travailler une terre excessivement humide.',
                    'Incorporer du compost mûr ou un amendement adapté.',
                    'Corriger le drainage et former des planches accessibles, généralement larges de 1 à 1,2 m selon le terrain et la saison.',
                    'Prévoir des passages pour l’entretien et la récolte.',
                ],
            ],
            [
                'title' => 'Préparer les semences ou le matériel de plantation',
                'items' => [
                    'Choisir des graines, plants, tubercules, bulbilles, caïeux, boutures ou rhizomes sains et de qualité connue.',
                    'Préparer les semences selon l’espèce : tri, trempage, traitement ou test de germination.',
                    'Vérifier la quantité nécessaire et la date de mise en culture.',
                ],
            ],
            [
                'title' => 'Réaliser le semis ou la plantation',
                'items' => [
                    'Choisir entre semis direct, semis en pépinière puis repiquage, ou plantation végétative.',
                    'Respecter la profondeur, l’espacement et la densité recommandés pour l’espèce et la variété.',
                    'Tasser légèrement autour du plant et maintenir le substrat humide après l’installation.',
                ],
            ],
            [
                'title' => 'Arroser',
                'items' => [
                    'Vérifier l’humidité du sol avant d’arroser.',
                    'Apporter de l’eau selon le stade de croissance, la météo et le type de sol, sans détremper.',
                    'Ajuster la fréquence en saison des pluies et pendant les fortes chaleurs.',
                    'Contrôler régulièrement l’état des plantes et des systèmes d’irrigation.',
                ],
            ],
            [
                'title' => 'Entretenir la culture',
                'items' => [
                    'Désherber régulièrement sans blesser les racines.',
                    'Remplacer les plants morts et éclaircir les semis trop denses.',
                    'Maintenir une humidité adaptée, surveiller les carences et apporter les fertilisants nécessaires.',
                    'Installer tuteurs, paillage ou buttage lorsque la culture le demande.',
                ],
            ],
            [
                'title' => 'Surveiller les maladies et les ravageurs',
                'items' => [
                    'Inspecter régulièrement les feuilles, les tiges, les racines et les fruits.',
                    'Identifier le symptôme puis la cause : insecte, maladie, problème nutritionnel ou stress hydrique.',
                    'Évaluer l’étendue des dégâts et privilégier la prévention et la lutte intégrée.',
                    'N’utiliser un produit phytosanitaire que s’il est approprié et autorisé pour la culture, en respectant l’étiquette et le délai avant récolte.',
                ],
            ],
            [
                'title' => 'Récolter',
                'items' => [
                    'Récolter au stade de maturité recherché : feuilles, fruits, racines, bulbes, tubercules, gousses ou inflorescences.',
                    'Récolter aux heures fraîches et limiter les blessures et la contamination.',
                    'Échelonner les passages lorsque la production est continue.',
                ],
            ],
            [
                'title' => 'Trier, conditionner et conserver',
                'items' => [
                    'Écarter les produits malades ou abîmés, puis trier par taille, maturité et qualité.',
                    'Nettoyer, égoutter ou sécher la surface lorsque cela convient au produit.',
                    'Conditionner dans des contenants propres et conserver à une température et une humidité adaptées.',
                    'Enregistrer les quantités récoltées, les pertes et les conditions de conservation.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{fields: array<string, string>, steps: list<array{title: string, items: list<string>}>}>
     */
    private function categoryProfiles(): array
    {
        return [
            'legumes-feuilles' => [
                'fields' => [
                    'overview' => 'Culture destinée à la production de feuilles ou de jeunes pousses. La qualité dépend fortement de l’eau, de l’azote et de la protection contre les insectes.',
                    'varieties' => 'Choisir une variété adaptée à la température locale, à la saison et au mode de commercialisation (feuilles fraîches, bottes, jeunes pousses).',
                    'climate' => 'Climat doux à chaud selon l’espèce ; éviter les fortes chaleurs qui provoquent la montée en graine et l’humidité stagnante favorable aux maladies.',
                    'soil' => 'Sol fertile, riche en matière organique, meuble et bien drainé. Un pH proche de 6 à 7 convient à la plupart des espèces, à confirmer par analyse.',
                    'land_preparation' => 'Nettoyer, ameublir, incorporer du compost mûr et former des planches drainées. En saison humide, surélever les planches.',
                    'planting_material' => 'Graines, plants de pépinière ou boutures selon l’espèce.',
                    'nursery' => 'Variable : pépinière pour la laitue et certaines brèdes, semis direct pour d’autres espèces.',
                    'sowing' => 'Semer clair, en lignes ou à la volée, à faible profondeur. Maintenir le substrat humide pendant la germination.',
                    'transplanting' => 'Repiquer les espèces concernées lorsque les plants ont 3 à 5 feuilles, puis arroser immédiatement.',
                    'irrigation' => 'Maintenir une humidité régulière sans excès. Arrosages légers et fréquents, surtout en saison sèche.',
                    'fertilization' => 'Apporter du compost à la préparation et un complément azoté modéré en cours de croissance. Éviter les excès.',
                    'maintenance' => 'Désherber, éclaircir, pailler si nécessaire et supprimer les feuilles malades.',
                    'pests_diseases' => 'Chenilles, pucerons, limaces, altises, mildiou et maladies favorisées par l’humidité.',
                    'cycle_duration' => 'Variable, environ 25 à 70 jours selon l’espèce et le mode de récolte.',
                    'harvest' => 'Récolter les feuilles jeunes ou adultes, progressivement ou en coupant la plante entière selon l’espèce.',
                    'expected_yield' => 'À estimer localement selon la variété, la densité et le nombre de coupes.',
                    'storage' => 'Conserver au frais et à humidité élevée ; commercialiser rapidement les feuilles fragiles.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Sélectionner une variété adaptée', [
                        'Tenir compte de la température locale, de la saison et de la tolérance aux maladies.',
                        'Choisir des semences ou des plants sains et de qualité connue.',
                    ]),
                    $this->step('Préparer une planche fertile et drainée', [
                        'Ameublir le sol et incorporer du compost mûr.',
                        'Former des planches accessibles et prévoir l’évacuation de l’eau.',
                    ]),
                    $this->step('Semer ou installer la pépinière', [
                        'Choisir le semis direct ou la pépinière selon l’espèce.',
                        'Maintenir une humidité régulière pendant la germination.',
                    ]),
                    $this->step('Éclaircir ou repiquer', [
                        'Éclaircir les semis directs trop denses.',
                        'Repiquer les espèces qui le nécessitent, sans enterrer le collet.',
                    ]),
                    $this->step('Maintenir l’humidité et la fertilité', [
                        'Arroser régulièrement sans détremper le sol.',
                        'Apporter une fertilisation équilibrée orientée vers la croissance des feuilles.',
                    ]),
                    $this->step('Protéger le feuillage', [
                        'Surveiller les insectes qui consomment les feuilles.',
                        'Prévenir les maladies favorisées par l’humidité et retirer les feuilles atteintes.',
                    ]),
                    $this->step('Récolter avec soin', [
                        'Récolter les feuilles progressivement ou couper la plante entière selon l’espèce.',
                        'Trier rapidement les feuilles fragiles.',
                    ]),
                    $this->step('Conditionner et conserver', [
                        'Éviter le flétrissement en conservant les feuilles au frais et à humidité élevée.',
                        'Nettoyer et conditionner dans des contenants propres.',
                    ]),
                ],
            ],
            'legumes-fruits' => [
                'fields' => [
                    'overview' => 'Culture conduite pour ses fruits. Le rendement dépend de la pollinisation, de l’alimentation en eau et de la protection contre les maladies du feuillage et des fruits.',
                    'varieties' => 'Choisir une variété adaptée au climat, à la période de production et au stade commercial recherché.',
                    'climate' => 'Climat chaud et lumineux pour la majorité des espèces ; éviter les excès de pluie, de froid et d’humidité stagnante.',
                    'soil' => 'Sol profond, fertile, bien drainé et riche en matière organique.',
                    'land_preparation' => 'Labourer, amender, corriger le drainage et prévoir un espace suffisant pour le développement des plants.',
                    'planting_material' => 'Graines et plants de pépinière pour les espèces repiquées ; semis direct possible pour plusieurs cucurbitacées.',
                    'nursery' => 'Recommandée pour la tomate, l’aubergine, le poivron et le piment ; facultative ou semis direct pour les courges, concombres et melons.',
                    'sowing' => 'Respecter la profondeur, l’espacement et la date de semis. Utiliser des godets pour les espèces à pépinière.',
                    'transplanting' => 'Repiquer au stade de 3 à 5 feuilles, sans enterrer le collet, puis arroser.',
                    'irrigation' => 'Irrigation régulière, particulièrement à la floraison et pendant la formation des fruits. Éviter de mouiller le feuillage.',
                    'fertilization' => 'Fumure de fond et apports équilibrés ; éviter l’excès d’azote au détriment de la fructification.',
                    'maintenance' => 'Tuteurage, taille, paillage et désherbage selon l’espèce. Surveiller la pollinisation.',
                    'pests_diseases' => 'Mildiou, oïdium, alternariose, fusariose, pucerons, acariens, chenilles et mouches des fruits.',
                    'cycle_duration' => 'Variable, environ 50 à 120 jours selon l’espèce, la variété et la saison.',
                    'harvest' => 'Récolter au stade commercial recherché, immature ou mûr. Manipuler les fruits avec soin et effectuer plusieurs passages si nécessaire.',
                    'expected_yield' => 'À estimer localement selon la variété, la conduite et les conditions sanitaires.',
                    'storage' => 'Conserver dans un local frais et ventilé, à l’abri des chocs. Certains fruits ont une conservation courte.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Choisir la variété et la période', [
                        'Adapter la variété au climat et au débouché commercial.',
                        'Prévoir la période de production et la disponibilité en eau.',
                    ]),
                    $this->step('Préparer le sol et le drainage', [
                        'Ameublir profondément et incorporer du compost mûr.',
                        'Former des planches surélevées en saison humide.',
                    ]),
                    $this->step('Réaliser la pépinière', [
                        'Semer en godets ou en planches abritées pour les espèces concernées.',
                        'Maintenir une humidité régulière et éviter l’étiolement.',
                    ]),
                    $this->step('Repiquer ou semer en place', [
                        'Repiquer les plants développés en respectant les distances.',
                        'Semer directement les espèces conduites sans repiquage.',
                    ]),
                    $this->step('Installer les supports', [
                        'Prévoir tuteurs ou treillis pour les cultures qui en ont besoin.',
                        'Attacher les plants sans serrer excessivement les tiges.',
                    ]),
                    $this->step('Conduire l’irrigation et la fertilisation', [
                        'Arroser régulièrement sans excès, surtout à la floraison.',
                        'Adapter la fertilisation aux stades de croissance et de fructification.',
                    ]),
                    $this->step('Surveiller la floraison et les fruits', [
                        'Observer la pollinisation et la formation des fruits.',
                        'Rechercher les symptômes de maladies et les dégâts d’insectes.',
                    ]),
                    $this->step('Récolter et trier', [
                        'Récolter selon le stade commercial recherché.',
                        'Trier et manipuler les fruits avec précaution.',
                    ]),
                ],
            ],
            'legumes-racines' => [
                'fields' => [
                    'overview' => 'Culture destinée à la racine comestible. La qualité dépend d’un sol profond, meuble, sans obstacles et d’une humidité régulière.',
                    'varieties' => 'Choisir une variété adaptée à la forme de racine recherchée, à la saison et à la durée de culture.',
                    'climate' => 'Climat frais à doux pour la majorité des espèces ; éviter les fortes chaleurs et les sols desséchés.',
                    'soil' => 'Sol profond, meuble, sans pierres, bien drainé et suffisamment fertile. Éviter les excès de fumure fraîche.',
                    'land_preparation' => 'Travailler profondément, retirer les pierres et préparer un lit de semences fin.',
                    'planting_material' => 'Graines, sauf pour les espèces multipliées par organes végétatifs.',
                    'nursery' => 'Généralement non : semis direct dans la parcelle.',
                    'sowing' => 'Semer directement, en lignes, à faible profondeur, dans un sol finement préparé.',
                    'transplanting' => 'Généralement non recommandé, car il peut déformer les racines.',
                    'irrigation' => 'Maintenir une humidité régulière pendant la germination et la formation des racines.',
                    'fertilization' => 'Apporter du compost mûr et du potassium ; éviter l’excès d’azote et de fumier frais.',
                    'maintenance' => 'Éclaircir, désherber sans blesser les racines et pailler si nécessaire.',
                    'pests_diseases' => 'Mouches des racines, nématodes, maladies du feuillage et pourritures en sol trop humide.',
                    'cycle_duration' => 'Variable, environ 25 à 120 jours selon l’espèce et la variété.',
                    'harvest' => 'Récolter lorsque les racines atteignent la taille souhaitée, sans les casser.',
                    'expected_yield' => 'À estimer localement selon la variété, la densité et la qualité du sol.',
                    'storage' => 'Conserver au frais, à l’abri de la déshydratation ; retirer les feuilles lorsque cela convient.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Choisir un sol adapté', [
                        'Préférer un sol profond, meuble et sans pierres.',
                        'Vérifier le drainage et la profondeur utile.',
                    ]),
                    $this->step('Préparer un lit de semences fin', [
                        'Retirer les obstacles et les mottes.',
                        'Incorporer un amendement mûr sans excès de fumier frais.',
                    ]),
                    $this->step('Semer directement', [
                        'Respecter la profondeur et l’espacement de l’espèce.',
                        'Maintenir le sol humide pendant la germination.',
                    ]),
                    $this->step('Éclaircir et désherber', [
                        'Éclaircir les plants trop rapprochés.',
                        'Désherber avec précaution pour ne pas blesser les racines.',
                    ]),
                    $this->step('Conduire l’eau et la fertilisation', [
                        'Maintenir une humidité régulière.',
                        'Apporter les nutriments nécessaires sans excès d’azote.',
                    ]),
                    $this->step('Surveiller les ravageurs souterrains', [
                        'Contrôler les mouches des racines et les nématodes.',
                        'Éviter l’humidité stagnante et les blessures.',
                    ]),
                    $this->step('Récolter et trier', [
                        'Extraire les racines sans les casser.',
                        'Trier et conserver selon l’espèce.',
                    ]),
                ],
            ],
            'legumes-tubercules' => [
                'fields' => [
                    'overview' => 'Culture destinée aux tubercules ou organes souterrains. La qualité du matériel de plantation et la gestion de l’humidité sont déterminantes.',
                    'varieties' => 'Choisir une variété adaptée à la région, au cycle disponible et à l’usage commercial ou familial.',
                    'climate' => 'Climat adapté à l’espèce ; éviter l’excès d’eau et les sols asphyxiants.',
                    'soil' => 'Sol meuble, profond et bien drainé, riche en matière organique.',
                    'land_preparation' => 'Ameublir profondément, corriger le drainage et préparer des buttes si nécessaire.',
                    'planting_material' => 'Tubercules de semence, boutures, éclats ou autres organes végétatifs sains.',
                    'nursery' => 'Généralement non : plantation directe du matériel végétatif.',
                    'sowing' => 'Planter à la profondeur et à l’espacement recommandés pour l’espèce.',
                    'transplanting' => 'Non applicable en général.',
                    'irrigation' => 'Arroser selon les besoins, sans détremper le sol ; réduire avant la récolte lorsque l’espèce le demande.',
                    'fertilization' => 'Apporter du compost et adapter les nutriments au développement des tubercules.',
                    'maintenance' => 'Désherber, buter les cultures qui le nécessitent et surveiller les parties souterraines.',
                    'pests_diseases' => 'Maladies des feuilles et des tubercules, nématodes, insectes du sol et pourritures.',
                    'cycle_duration' => 'Variable, de quelques mois à près d’un an selon l’espèce.',
                    'harvest' => 'Récolter au stade de maturité approprié, en limitant les blessures.',
                    'expected_yield' => 'À estimer localement selon la variété, le matériel de plantation et le sol.',
                    'storage' => 'Sécher ou cicatriser les organes récoltés lorsque cela convient, puis conserver au frais, à l’abri de l’humidité et de la lumière.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Choisir un matériel de plantation sain', [
                        'Sélectionner des tubercules, boutures ou éclats indemnes de maladie.',
                        'Adapter la quantité et la variété à la surface prévue.',
                    ]),
                    $this->step('Préparer un sol meuble et drainé', [
                        'Ameublir profondément et corriger les problèmes de drainage.',
                        'Former des buttes ou des planches adaptées.',
                    ]),
                    $this->step('Planter au bon espacement', [
                        'Respecter la profondeur et la distance recommandées.',
                        'Maintenir une humidité suffisante après la plantation.',
                    ]),
                    $this->step('Entretenir et butter', [
                        'Désherber pendant l’installation.',
                        'Butter les cultures qui le nécessitent.',
                    ]),
                    $this->step('Surveiller les maladies', [
                        'Contrôler le feuillage et les organes souterrains.',
                        'Éviter l’excès d’eau et les blessures.',
                    ]),
                    $this->step('Récolter et conserver', [
                        'Récolter au stade de maturité approprié.',
                        'Sécher, cicatriser et stocker dans de bonnes conditions.',
                    ]),
                ],
            ],
            'legumes-a-bulbes' => [
                'fields' => [
                    'overview' => 'Culture destinée au bulbe, au pseudo-tronc ou aux feuilles. La maturation et le séchage sont essentielles pour la conservation.',
                    'varieties' => 'Choisir une variété adaptée à la durée du jour, au climat et à la période de récolte.',
                    'climate' => 'Climat adapté à l’espèce ; éviter l’excès d’humidité pendant la maturation.',
                    'soil' => 'Sol fertile, meuble et bien drainé.',
                    'land_preparation' => 'Ameublir, incorporer du compost mûr et former des planches drainées.',
                    'planting_material' => 'Graines, bulbilles, caïeux ou plants selon l’espèce.',
                    'nursery' => 'Variable : pépinière pour l’oignon cultivé à partir de graines, plantation directe pour l’ail et l’échalote.',
                    'sowing' => 'Respecter la profondeur, l’espacement et la date de semis ou de plantation.',
                    'transplanting' => 'Repiquer les plants d’oignon lorsque cela est prévu.',
                    'irrigation' => 'Contrôler régulièrement l’humidité ; réduire l’eau à l’approche de la maturité.',
                    'fertilization' => 'Apporter un compost mûr et les nutriments nécessaires, sans excès d’azote.',
                    'maintenance' => 'Désherber avec précaution, surveiller les maladies des feuilles et des bulbes.',
                    'pests_diseases' => 'Mildiou, pourriture du bulbe, thrips, mouches et nématodes.',
                    'cycle_duration' => 'Variable, environ 100 à 210 jours selon l’espèce et la variété.',
                    'harvest' => 'Récolter au stade de maturation, lorsque les feuilles jaunissent ou que le bulbe atteint sa taille.',
                    'expected_yield' => 'À estimer localement selon la variété, la densité et la qualité du sol.',
                    'storage' => 'Sécher les bulbes, retirer les parties abîmées et conserver dans un local sec et ventilé.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Choisir la variété et le matériel', [
                        'Adapter la variété à la durée du jour et au climat.',
                        'Choisir entre semences, bulbilles ou caïeux selon l’espèce.',
                    ]),
                    $this->step('Préparer une parcelle drainée', [
                        'Ameublir le sol et incorporer du compost mûr.',
                        'Éviter les zones où l’eau stagne.',
                    ]),
                    $this->step('Semer ou planter', [
                        'Respecter la profondeur et l’espacement.',
                        'Maintenir une humidité régulière après l’installation.',
                    ]),
                    $this->step('Désherber et fertiliser', [
                        'Désherber sans blesser les bulbes.',
                        'Apporter les nutriments nécessaires sans excès d’azote.',
                    ]),
                    $this->step('Surveiller les maladies', [
                        'Contrôler les feuilles et les bulbes.',
                        'Éviter l’humidité stagnante.',
                    ]),
                    $this->step('Récolter et sécher', [
                        'Identifier le stade de maturation.',
                        'Sécher les bulbes lorsque cela est nécessaire.',
                    ]),
                    $this->step('Trier et stocker', [
                        'Écarter les bulbes abîmés.',
                        'Stocker dans un local sec et ventilé.',
                    ]),
                ],
            ],
            'legumes-a-tiges' => [
                'fields' => [
                    'overview' => 'Culture destinée à la tige, au pétiole ou à la pousse comestible. L’alimentation en eau et la protection contre l’éclatement ou la sécheresse sont importantes.',
                    'varieties' => 'Choisir une variété adaptée au climat, à la saison et au mode de commercialisation.',
                    'climate' => 'Climat adapté à l’espèce ; éviter les stress hydriques et les fortes chaleurs selon la culture.',
                    'soil' => 'Sol profond, fertile, frais et bien drainé.',
                    'land_preparation' => 'Ameublir profondément, incorporer du compost et préparer des planches.',
                    'planting_material' => 'Graines, plants, couronnes, griffes ou rhizomes selon l’espèce.',
                    'nursery' => 'Variable selon l’espèce.',
                    'sowing' => 'Respecter la profondeur et l’espacement propres à la culture.',
                    'transplanting' => 'Repiquer lorsque l’espèce le nécessite.',
                    'irrigation' => 'Maintenir une humidité régulière, sans excès.',
                    'fertilization' => 'Apporter du compost et une fertilisation équilibrée.',
                    'maintenance' => 'Désherber, butter ou blanchir les tiges lorsque cela convient.',
                    'pests_diseases' => 'Pucerons, chenilles, maladies foliaires et pourritures liées à l’humidité.',
                    'cycle_duration' => 'Variable selon l’espèce ; certaines cultures sont pérennes.',
                    'harvest' => 'Récolter les tiges ou pétioles au stade tendre et commercial.',
                    'expected_yield' => 'À estimer localement selon la variété et le mode de conduite.',
                    'storage' => 'Conserver au frais et à humidité élevée pour les tiges fraîches.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Préparer un sol profond et frais', [
                        'Ameublir et incorporer du compost mûr.',
                        'Prévoir un bon drainage.',
                    ]),
                    $this->step('Installer la culture', [
                        'Choisir semences, plants ou organes végétatifs adaptés.',
                        'Respecter la profondeur et l’espacement.',
                    ]),
                    $this->step('Maintenir l’humidité', [
                        'Arroser régulièrement sans détremper.',
                        'Pailler si nécessaire.',
                    ]),
                    $this->step('Entretenir les tiges', [
                        'Désherber et butter lorsque cela convient.',
                        'Protéger les tiges des blessures.',
                    ]),
                    $this->step('Récolter et conserver', [
                        'Récolter au stade tendre.',
                        'Conserver au frais et à humidité élevée.',
                    ]),
                ],
            ],
            'legumes-fleurs' => [
                'fields' => [
                    'overview' => 'Culture destinée à l’inflorescence. La qualité dépend de la croissance régulière, de la protection contre les ravageurs et de la récolte au bon stade.',
                    'varieties' => 'Choisir une variété adaptée aux températures locales et à la période de récolte.',
                    'climate' => 'Climat frais à doux pour les choux-fleurs et brocolis ; l’artichaut a des exigences différentes.',
                    'soil' => 'Sol fertile, profond et bien drainé.',
                    'land_preparation' => 'Ameublir, incorporer du compost et former des planches drainées.',
                    'planting_material' => 'Graines et plants de pépinière pour les brassicacées ; œilletons ou rejets pour l’artichaut.',
                    'nursery' => 'Oui pour les choux-fleurs, brocolis et romanesco ; variable pour l’artichaut.',
                    'sowing' => 'Semer en pépinière à faible profondeur et maintenir humide.',
                    'transplanting' => 'Repiquer au stade approprié en respectant les distances.',
                    'irrigation' => 'Maintenir une alimentation en eau régulière.',
                    'fertilization' => 'Apporter du compost et les nutriments nécessaires à la croissance.',
                    'maintenance' => 'Désherber, pailler et protéger les inflorescences.',
                    'pests_diseases' => 'Chenilles, pucerons, mouches, maladies foliaires et pourritures.',
                    'cycle_duration' => 'Variable, environ 70 à 150 jours selon l’espèce et la variété.',
                    'harvest' => 'Récolter avant la dégradation de la qualité commerciale.',
                    'expected_yield' => 'À estimer localement selon la variété et les conditions de croissance.',
                    'storage' => 'Refroidir rapidement et conserver au frais pour les inflorescences fraîches.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Choisir la variété', [
                        'Adapter la variété aux températures locales.',
                        'Prévoir la période de récolte.',
                    ]),
                    $this->step('Préparer le sol', [
                        'Ameublir et incorporer du compost.',
                        'Former des planches drainées.',
                    ]),
                    $this->step('Réaliser la pépinière', [
                        'Semer dans un substrat propre.',
                        'Maintenir une humidité régulière.',
                    ]),
                    $this->step('Repiquer', [
                        'Repiquer au stade approprié.',
                        'Respecter les distances de plantation.',
                    ]),
                    $this->step('Conduire l’eau et la fertilisation', [
                        'Arroser régulièrement.',
                        'Fournir les nutriments nécessaires à la croissance.',
                    ]),
                    $this->step('Protéger les inflorescences', [
                        'Surveiller chenilles, pucerons et maladies.',
                        'Éviter les dommages aux parties commercialisées.',
                    ]),
                    $this->step('Récolter et trier', [
                        'Récolter avant la dégradation de la qualité.',
                        'Refroidir rapidement si nécessaire.',
                    ]),
                ],
            ],
            'legumes-a-gousses-et-graines' => [
                'fields' => [
                    'overview' => 'Culture destinée aux gousses ou aux graines fraîches. La fixation de l’azote et le respect du stade de récolte sont des points clés.',
                    'varieties' => 'Choisir une variété adaptée au climat, au port (nain ou grimpant) et à l’usage prévu.',
                    'climate' => 'Climat adapté à l’espèce ; éviter les excès de chaleur et d’humidité pendant la floraison.',
                    'soil' => 'Sol meuble, bien drainé et suffisamment fertile, sans excès d’azote.',
                    'land_preparation' => 'Ameublir le terrain et incorporer du compost mature sans surfertiliser en azote.',
                    'planting_material' => 'Semences saines de qualité connue.',
                    'nursery' => 'Généralement non : semis direct.',
                    'sowing' => 'Semer directement à la profondeur et à l’espacement recommandés.',
                    'transplanting' => 'Non recommandé en général.',
                    'irrigation' => 'Arroser pendant l’installation et les phases sensibles, sans excès.',
                    'fertilization' => 'Éviter l’excès d’azote ; apporter du phosphore et du potassium selon les besoins.',
                    'maintenance' => 'Désherber et installer des supports pour les variétés grimpantes.',
                    'pests_diseases' => 'Pucerons, chenilles, maladies foliaires et pourritures des gousses.',
                    'cycle_duration' => 'Variable, environ 50 à 120 jours selon l’espèce et la variété.',
                    'harvest' => 'Récolter les gousses au stade souhaité, en plusieurs passages si la production est échelonnée.',
                    'expected_yield' => 'À estimer localement selon la variété, le port et les conditions de culture.',
                    'storage' => 'Conserver rapidement les produits frais au frais ; sécher les graines si elles sont destinées au stockage.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Choisir la variété et les semences', [
                        'Adapter la variété au climat et à l’usage.',
                        'Sélectionner des semences saines.',
                    ]),
                    $this->step('Préparer le terrain', [
                        'Ameublir et éviter l’excès d’azote.',
                        'Prévoir l’espace nécessaire aux variétés grimpantes.',
                    ]),
                    $this->step('Semer directement', [
                        'Respecter la profondeur et l’espacement.',
                        'Maintenir une humidité suffisante à l’installation.',
                    ]),
                    $this->step('Installer les supports', [
                        'Prévoir tuteurs ou rames pour les variétés grimpantes.',
                        'Attacher sans blesser les tiges.',
                    ]),
                    $this->step('Surveiller la floraison et les gousses', [
                        'Observer la floraison et la formation des gousses.',
                        'Surveiller insectes et maladies.',
                    ]),
                    $this->step('Récolter par passages', [
                        'Récolter au stade souhaité.',
                        'Effectuer plusieurs passages si nécessaire.',
                    ]),
                    $this->step('Trier et conserver', [
                        'Trier rapidement les produits récoltés.',
                        'Conserver au frais ou sécher selon l’usage.',
                    ]),
                ],
            ],
            'legumes-aromatiques' => [
                'fields' => [
                    'overview' => 'Plante aromatique ou condimentaire. La qualité est liée à la teneur en huiles essentielles, à la fraîcheur et à la maîtrise de l’humidité.',
                    'varieties' => 'Choisir une variété adaptée au climat, à l’usage culinaire et au mode de conservation.',
                    'climate' => 'Climat adapté à l’espèce ; protéger les plantes sensibles au froid ou à l’excès d’humidité.',
                    'soil' => 'Sol léger, fertile et bien drainé.',
                    'land_preparation' => 'Ameublir, incorporer du compost mûr et éviter les sols compactés.',
                    'planting_material' => 'Graines, boutures, divisions ou plants selon l’espèce.',
                    'nursery' => 'Variable : semis direct pour certaines espèces, pépinière pour d’autres.',
                    'sowing' => 'Semer à faible profondeur et maintenir une humidité modérée.',
                    'transplanting' => 'Repiquer les espèces qui le nécessitent, sans enterrer le collet.',
                    'irrigation' => 'Arroser modérément mais régulièrement ; éviter l’excès d’eau.',
                    'fertilization' => 'Apporter du compost et éviter les excès d’azote qui diluent les arômes.',
                    'maintenance' => 'Désherber, tailler légèrement et supprimer les parties malades.',
                    'pests_diseases' => 'Pucerons, aleurodes, maladies foliaires et pourritures liées à l’humidité.',
                    'cycle_duration' => 'Variable, de quelques semaines à plusieurs mois selon l’espèce.',
                    'harvest' => 'Récolter les feuilles ou tiges au moment où les arômes sont les plus développés.',
                    'expected_yield' => 'À estimer localement selon l’espèce et le mode de récolte.',
                    'storage' => 'Consommer frais, sécher à l’ombre ou congeler selon l’espèce.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Choisir l’espèce et l’emplacement', [
                        'Adapter l’espèce au climat et à l’ensoleillement.',
                        'Prévoir un sol drainé.',
                    ]),
                    $this->step('Installer la culture', [
                        'Semer, bouturer ou repiquer selon l’espèce.',
                        'Respecter l’espacement.',
                    ]),
                    $this->step('Conduire l’eau et la fertilisation', [
                        'Arroser modérément.',
                        'Éviter les excès d’azote.',
                    ]),
                    $this->step('Tailler et entretenir', [
                        'Supprimer les parties malades.',
                        'Tailler légèrement pour favoriser la repousse.',
                    ]),
                    $this->step('Récolter et conserver', [
                        'Récolter au bon stade aromatique.',
                        'Sécher, congeler ou consommer frais.',
                    ]),
                ],
            ],
            'champignons' => [
                'fields' => [
                    'overview' => 'Production de champignons sur substrat. Elle ne suit pas le cycle d’une plante maraîchère et exige une hygiène stricte.',
                    'varieties' => 'Choisir une espèce et une souche adaptées au substrat, au local et au débouché commercial.',
                    'climate' => 'Température, humidité et ventilation doivent être contrôlées selon l’espèce et le stade de culture.',
                    'soil' => 'Pas de sol maraîcher : la culture se fait sur un substrat préparé ou acheté.',
                    'land_preparation' => 'Préparer un local propre, désinfecté et adapté à la culture.',
                    'planting_material' => 'Mycélium sain et substrat adapté à l’espèce.',
                    'nursery' => 'Non applicable : production sur substrat.',
                    'sowing' => 'Ensemencer le substrat avec le mycélium selon le procédé recommandé.',
                    'transplanting' => 'Non applicable.',
                    'irrigation' => 'Gérer l’humidité du substrat et de l’air sans détremper.',
                    'fertilization' => 'La nutrition vient du substrat préparé ; aucun engrais maraîcher classique.',
                    'maintenance' => 'Contrôler température, humidité, ventilation et contaminations.',
                    'pests_diseases' => 'Moisissures, bactéries, moucherons et contaminations du substrat.',
                    'cycle_duration' => 'Variable selon l’espèce et le procédé, de quelques semaines à plusieurs mois.',
                    'harvest' => 'Récolter les champignons au stade commercial, avec des instruments propres.',
                    'expected_yield' => 'À estimer localement selon l’espèce, le substrat et la maîtrise technique.',
                    'storage' => 'Conserver au frais et à humidité adaptée ; commercialiser rapidement.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Choisir l’espèce et la souche', [
                        'Adapter l’espèce au local et au débouché.',
                        'Utiliser un mycélium sain.',
                    ]),
                    $this->step('Préparer le substrat et le local', [
                        'Assainir le matériel et les locaux.',
                        'Préparer ou acheter un substrat adapté.',
                    ]),
                    $this->step('Ensemencer le substrat', [
                        'Incorporer le mycélium selon le procédé.',
                        'Maintenir les conditions de colonisation.',
                    ]),
                    $this->step('Conduire la fructification', [
                        'Ajuster température, humidité et ventilation.',
                        'Surveiller les contaminations.',
                    ]),
                    $this->step('Récolter et conserver', [
                        'Récolter au bon stade.',
                        'Trier et conserver au frais.',
                    ]),
                ],
            ],
            'legumes-tropicaux' => [
                'fields' => [
                    'overview' => 'Espèce tropicale ou légume moins courant adapté au contexte malgache. La réussite dépend de l’adaptation variétale et de la maîtrise de l’eau.',
                    'varieties' => 'Choisir une variété ou un écotype adapté à la zone, à la saison et à l’usage local.',
                    'climate' => 'Climat chaud ou tropical ; protéger les cultures sensibles pendant les périodes froides ou trop sèches.',
                    'soil' => 'Sol fertile, meuble et bien drainé, adapté à l’espèce.',
                    'land_preparation' => 'Nettoyer, ameublir et former des planches adaptées à la saison.',
                    'planting_material' => 'Semences, boutures, tubercules ou plants selon l’espèce.',
                    'nursery' => 'Variable selon l’espèce.',
                    'sowing' => 'Respecter la profondeur, l’espacement et la saison favorables.',
                    'transplanting' => 'Repiquer les espèces qui le nécessitent.',
                    'irrigation' => 'Maintenir une humidité régulière sans excès ; pailler si nécessaire.',
                    'fertilization' => 'Apporter du compost et adapter les nutriments à l’espèce.',
                    'maintenance' => 'Désherber, pailler, tuteurer ou butter selon la culture.',
                    'pests_diseases' => 'Surveiller les ravageurs et maladies locales de l’espèce.',
                    'cycle_duration' => 'Variable selon l’espèce et la zone de culture.',
                    'harvest' => 'Récolter au stade local de consommation ou de commercialisation.',
                    'expected_yield' => 'À estimer localement selon l’écotype et les conditions.',
                    'storage' => 'Adapter la conservation à l’organe récolté et au climat.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Choisir une espèce adaptée', [
                        'Vérifier l’adaptation au climat et au sol local.',
                        'Identifier le débouché commercial.',
                    ]),
                    $this->step('Préparer le terrain', [
                        'Nettoyer et ameublir le sol.',
                        'Prévoir le drainage et l’irrigation.',
                    ]),
                    $this->step('Installer la culture', [
                        'Utiliser des semences ou du matériel végétatif sain.',
                        'Respecter les distances et la profondeur.',
                    ]),
                    $this->step('Conduire la culture', [
                        'Arroser, désherber et pailler.',
                        'Surveiller les ravageurs et les maladies.',
                    ]),
                    $this->step('Récolter et conserver', [
                        'Récolter au stade adapté.',
                        'Trier et conserver selon l’organe récolté.',
                    ]),
                ],
            ],
            'autres' => [
                'fields' => [
                    'overview' => 'Fiche générique à adapter à l’espèce. Les travaux et les valeurs doivent être précisés à partir de références locales.',
                    'varieties' => 'Choisir une variété adaptée au climat, à la saison et au débouché commercial.',
                    'climate' => 'Vérifier les exigences de l’espèce et l’adaptation au climat local.',
                    'soil' => 'Sol fertile, meuble et bien drainé, adapté à l’espèce.',
                    'land_preparation' => 'Nettoyer, ameublir et amender selon les besoins de la culture.',
                    'planting_material' => 'Semences ou matériel végétatif sain selon l’espèce.',
                    'nursery' => 'À préciser selon l’espèce.',
                    'sowing' => 'Respecter la profondeur et l’espacement recommandés.',
                    'transplanting' => 'À préciser selon l’espèce.',
                    'irrigation' => 'Maintenir une humidité adaptée sans excès.',
                    'fertilization' => 'Apporter du compost et les nutriments nécessaires.',
                    'maintenance' => 'Désherber, entretenir et protéger la culture.',
                    'pests_diseases' => 'Surveiller les ravageurs et maladies de l’espèce.',
                    'cycle_duration' => 'À préciser selon la variété et la région.',
                    'harvest' => 'Récolter au stade de maturité recherché.',
                    'expected_yield' => 'À estimer localement.',
                    'storage' => 'Adapter la conservation à l’organe récolté.',
                    'sources' => $this->sourcesText(),
                ],
                'steps' => [
                    $this->step('Préparer la culture', [
                        'Vérifier l’espèce, la variété et l’adaptation locale.',
                        'Préparer le sol et le matériel de plantation.',
                    ]),
                    $this->step('Installer et entretenir', [
                        'Semer ou planter selon les recommandations.',
                        'Arroser, désherber et fertiliser.',
                    ]),
                    $this->step('Récolter et conserver', [
                        'Récolter au stade adapté.',
                        'Trier et conserver dans de bonnes conditions.',
                    ]),
                ],
            ],
        ];
    }

    /**
     * @return array{fields?: array<string, string>, steps?: list<array{title: string, items: list<string>}>}
     */
    private function specificProfileFor(string $name): array
    {
        foreach ($this->cropProfiles() as $profile) {
            if (in_array($name, $profile['aliases'], true)) {
                return $profile;
            }
        }

        return [];
    }

    /**
     * @return list<array{aliases: list<string>, fields: array<string, string>, steps: list<array{title: string, items: list<string>}>}>
     */
    private function cropProfiles(): array
    {
        return [
            [
                'aliases' => ['Tomate', 'Tomate cerise'],
                'fields' => [
                    'varieties' => 'Variétés adaptées au contexte local : Mercure, Roma, Mongal, variétés cerises. Vérifier la résistance aux maladies et l’adaptation à la saison.',
                    'climate' => 'Climat chaud et ensoleillé ; éviter les températures extrêmes, les pluies prolongées et l’humidité stagnante.',
                    'soil' => 'Sol profond, meuble, riche en matière organique et bien drainé ; pH proche de 6 à 7.',
                    'land_preparation' => 'Labour profond, compost mûr, planches surélevées en saison humide et paillage.',
                    'planting_material' => 'Graines saines et plants de pépinière.',
                    'nursery' => 'Oui : pépinière bien drainée et abritée, en godets ou en planches.',
                    'sowing' => 'Semis en pépinière à faible profondeur, avec une humidité régulière.',
                    'transplanting' => 'Repiquer au stade de 4 à 6 feuilles, en respectant 60 à 80 cm entre plants selon la variété.',
                    'irrigation' => 'Arroser au pied, sans mouiller le feuillage ; renforcer à la floraison et à la fructification.',
                    'fertilization' => 'Compost de fond, phosphore et potasse ; azote modéré et apports fractionnés.',
                    'maintenance' => 'Tuteurage, taille des gourmands selon la variété, paillage et désherbage.',
                    'pests_diseases' => 'Mildiou, alternariose, fusariose, flétrissement bactérien, pucerons, acariens, chenilles et mouches des fruits.',
                    'cycle_duration' => 'Environ 75 à 95 jours après repiquage selon la variété et le climat.',
                    'harvest' => 'Récolter les fruits mûrs ou au stade commercial, plusieurs fois par semaine.',
                    'storage' => 'Conserver à l’ombre, au frais et ventilé ; éviter les chocs et l’empilement excessif.',
                ],
                'steps' => [
                    $this->step('Réussir la pépinière et le repiquage', [
                        'Semer en godets ou en planches abritées.',
                        'Repiquer des plants vigoureux sans enterrer le collet.',
                    ]),
                    $this->step('Conduire et tuteurer', [
                        'Installer les tuteurs avant que les plants ne versent.',
                        'Tailler les gourmands selon la variété et le mode de conduite.',
                    ]),
                    $this->step('Récolter de façon échelonnée', [
                        'Passer plusieurs fois par semaine.',
                        'Récolter au stade commercial et trier les fruits.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Carotte'],
                'fields' => [
                    'soil' => 'Sol profond, meuble, sans pierres et bien drainé ; éviter les fumures fraîches.',
                    'land_preparation' => 'Travailler profondément, retirer les pierres et préparer un lit de semences fin.',
                    'planting_material' => 'Graines fraîches de qualité.',
                    'nursery' => 'Non : semis direct.',
                    'sowing' => 'Semer en lignes à faible profondeur, à densité suffisante, puis maintenir humide.',
                    'transplanting' => 'Non recommandé : le repiquage déforme souvent les racines.',
                    'maintenance' => 'Éclaircir dès que les plants se touchent, désherber et pailler.',
                    'cycle_duration' => 'Environ 90 à 110 jours selon la variété et la saison.',
                    'harvest' => 'Récolter lorsque les racines atteignent la taille souhaitée, sans les casser.',
                    'storage' => 'Conserver au frais et à humidité élevée, après avoir retiré les feuilles.',
                ],
                'steps' => [
                    $this->step('Préparer un lit de semences fin', [
                        'Retirer pierres et mottes.',
                        'Éviter le fumier frais.',
                    ]),
                    $this->step('Semer et éclaircir', [
                        'Semer direct en lignes.',
                        'Éclaircir pour laisser la racine se développer.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Oignon', 'Oignon blanc', 'Oignon rouge', 'Oignon jaune', 'Oignon perlé'],
                'fields' => [
                    'climate' => 'Climat adapté à la durée du jour et à la saison ; éviter l’excès d’humidité pendant la maturation.',
                    'soil' => 'Sol fertile, meuble et bien drainé.',
                    'planting_material' => 'Graines, bulbilles ou plants selon le système de culture.',
                    'nursery' => 'Possible pour les semis de graines ; plantation directe pour les bulbilles.',
                    'sowing' => 'Semer ou planter à faible profondeur en respectant l’espacement.',
                    'irrigation' => 'Arroser régulièrement puis réduire à l’approche de la maturité.',
                    'fertilization' => 'Compost mûr et fertilisation modérée ; éviter l’excès d’azote.',
                    'maintenance' => 'Désherber, surveiller les maladies foliaires et la formation du bulbe.',
                    'pests_diseases' => 'Mildiou, pourriture du bulbe, thrips et mouches.',
                    'cycle_duration' => 'Environ 110 à 150 jours selon la variété et le mode de culture.',
                    'harvest' => 'Récolter lorsque les feuilles jaunissent et que le bulbe est formé.',
                    'storage' => 'Sécher les bulbes, retirer les parties abîmées et stocker au sec et au frais.',
                ],
                'steps' => [
                    $this->step('Conduire la formation du bulbe', [
                        'Respecter la densité et l’espacement.',
                        'Réduire l’irrigation à l’approche de la maturité.',
                    ]),
                    $this->step('Sécher et conserver', [
                        'Arracher les bulbes au bon stade.',
                        'Sécher avant stockage dans un local ventilé.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Ail'],
                'fields' => [
                    'planting_material' => 'Caïeux sains et de qualité connue.',
                    'nursery' => 'Non : plantation directe des caïeux.',
                    'sowing' => 'Planter les caïeux à la profondeur recommandée, pointe vers le haut.',
                    'irrigation' => 'Maintenir une humidité modérée puis réduire avant la récolte.',
                    'cycle_duration' => 'Environ 150 à 210 jours selon la variété et la région.',
                    'harvest' => 'Récolter lorsque les feuilles jaunissent et se couchent.',
                    'storage' => 'Sécher les bulbes à l’ombre, puis conserver au sec et ventilé.',
                ],
                'steps' => [
                    $this->step('Planter des caïeux sains', [
                        'Choisir des caïeux indemnes de maladie.',
                        'Respecter la profondeur et l’espacement.',
                    ]),
                    $this->step('Récolter et sécher', [
                        'Attendre le jaunissement des feuilles.',
                        'Sécher avant stockage.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Pomme de terre', 'Pomme de terre violette'],
                'fields' => [
                    'planting_material' => 'Tubercules de semence sains et certifiés lorsque possible.',
                    'nursery' => 'Non : plantation directe.',
                    'sowing' => 'Planter les tubercules à la profondeur et à l’espacement recommandés.',
                    'maintenance' => 'Butter les plants et surveiller le feuillage et les tubercules.',
                    'cycle_duration' => 'Environ 90 à 120 jours selon la variété et la saison.',
                    'harvest' => 'Récolter à maturité, après jaunissement du feuillage ou selon l’usage.',
                    'storage' => 'Conserver au frais, à l’obscurité et dans un local ventilé.',
                ],
                'steps' => [
                    $this->step('Planter et butter', [
                        'Utiliser des tubercules de semence sains.',
                        'Butter pour favoriser la formation des tubercules.',
                    ]),
                    $this->step('Récolter et stocker', [
                        'Récolter à maturité.',
                        'Conserver à l’abri de la lumière.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Patate douce'],
                'fields' => [
                    'planting_material' => 'Boutures saines issues de plants vigoureux.',
                    'nursery' => 'Non : plantation de boutures.',
                    'sowing' => 'Planter les boutures en buttes ou en planches, à l’espacement recommandé.',
                    'maintenance' => 'Désherber, butter si nécessaire et éviter l’asphyxie des racines.',
                    'cycle_duration' => 'Environ 100 à 150 jours selon la variété et la région.',
                    'harvest' => 'Récolter lorsque les racines tubérisées atteignent la taille commerciale.',
                    'storage' => 'Cicatriser les racines puis conserver au sec et à température modérée.',
                ],
                'steps' => [
                    $this->step('Planter des boutures', [
                        'Choisir des boutures saines.',
                        'Installer sur buttes ou planches drainées.',
                    ]),
                    $this->step('Récolter et cicatriser', [
                        'Récolter au bon stade.',
                        'Laisser cicatriser avant stockage.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Laitue'],
                'fields' => [
                    'nursery' => 'Possible et souvent recommandée pour maîtriser l’espacement.',
                    'sowing' => 'Semer en pépinière ou directement, à faible profondeur.',
                    'transplanting' => 'Repiquer lorsque les plants ont 3 à 5 feuilles.',
                    'irrigation' => 'Maintenir une humidité régulière ; éviter les fortes chaleurs et la sécheresse.',
                    'cycle_duration' => 'Environ 45 à 70 jours selon la variété et la saison.',
                    'harvest' => 'Récolter la pomme ou les feuilles au stade commercial.',
                    'storage' => 'Conserver au frais et à humidité élevée.',
                ],
                'steps' => [
                    $this->step('Maîtriser la chaleur et l’humidité', [
                        'Semer à une période adaptée.',
                        'Arroser régulièrement sans détremper.',
                    ]),
                    $this->step('Récolter rapidement', [
                        'Récolter avant la montée en graine.',
                        'Conserver au frais.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Chou-fleur', 'Chou-fleur blanc', 'Chou-fleur violet', 'Chou-fleur vert'],
                'fields' => [
                    'nursery' => 'Oui : pépinière pour produire des plants vigoureux.',
                    'transplanting' => 'Repiquer au stade approprié en respectant les distances.',
                    'irrigation' => 'Maintenir une alimentation en eau régulière.',
                    'maintenance' => 'Protéger l’inflorescence du soleil et des ravageurs si nécessaire.',
                    'cycle_duration' => 'Environ 70 à 100 jours selon la variété et la saison.',
                    'harvest' => 'Récolter avant que l’inflorescence ne se dégrade.',
                    'storage' => 'Refroidir rapidement et conserver au frais.',
                ],
                'steps' => [
                    $this->step('Former une belle inflorescence', [
                        'Assurer une croissance régulière.',
                        'Protéger la pomme des dommages.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Concombre', 'Cornichon'],
                'fields' => [
                    'nursery' => 'Facultative : semis direct ou plants de pépinière.',
                    'sowing' => 'Semer à la profondeur recommandée, en respectant l’espacement.',
                    'maintenance' => 'Conduire au sol ou sur support selon la variété ; pailler et désherber.',
                    'cycle_duration' => 'Environ 50 à 70 jours selon la variété et la saison.',
                    'harvest' => 'Récolter fréquemment les fruits au stade commercial.',
                    'storage' => 'Conserver au frais ; la conservation est courte.',
                ],
                'steps' => [
                    $this->step('Choisir la conduite', [
                        'Décider entre culture au sol ou sur support.',
                        'Prévoir l’espace et les tuteurs.',
                    ]),
                    $this->step('Récolter souvent', [
                        'Passer régulièrement pour récolter les jeunes fruits.',
                        'Éviter de laisser les fruits devenir trop gros.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Gombo'],
                'fields' => [
                    'sowing' => 'Semis direct à la profondeur et à l’espacement recommandés.',
                    'maintenance' => 'Désherber et surveiller la croissance des plants.',
                    'cycle_duration' => 'Environ 50 à 70 jours selon la variété et la région.',
                    'harvest' => 'Récolter fréquemment les jeunes fruits avant qu’ils ne deviennent fibreux.',
                    'storage' => 'Conserver au frais et consommer rapidement.',
                ],
                'steps' => [
                    $this->step('Récolter au bon stade', [
                        'Récolter les jeunes gousses fréquemment.',
                        'Ne pas attendre que les fruits deviennent fibreux.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Haricot vert', 'Haricot beurre', 'Haricot plat', 'Haricot kilomètre'],
                'fields' => [
                    'soil' => 'Sol meuble, bien drainé, sans excès d’azote.',
                    'planting_material' => 'Semences saines adaptées au port nain ou grimpant.',
                    'nursery' => 'Non : semis direct.',
                    'sowing' => 'Semer directement à la profondeur et à l’espacement recommandés.',
                    'maintenance' => 'Installer des supports pour les variétés grimpantes et désherber.',
                    'cycle_duration' => 'Environ 50 à 70 jours selon la variété.',
                    'harvest' => 'Récolter les gousses au stade tendre, en plusieurs passages.',
                    'storage' => 'Conserver au frais et commercialiser rapidement.',
                ],
                'steps' => [
                    $this->step('Choisir le port et les supports', [
                        'Distinguer variétés naines et grimpantes.',
                        'Prévoir des supports pour les variétés grimpantes.',
                    ]),
                    $this->step('Récolter au stade tendre', [
                        'Effectuer plusieurs passages.',
                        'Trier les gousses avant conditionnement.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Cresson'],
                'fields' => [
                    'climate' => 'Milieu frais et humide ; éviter les eaux stagnantes et contaminées.',
                    'soil' => 'Sol ou support constamment humide, adapté à la culture du cresson.',
                    'irrigation' => 'Gestion spécifique de l’eau : humidité constante et eau propre.',
                    'cycle_duration' => 'Environ 30 à 60 jours selon le mode de culture.',
                    'harvest' => 'Récolter les feuilles ou jeunes pousses avant la floraison.',
                    'storage' => 'Conserver au frais et à humidité élevée ; consommer rapidement.',
                ],
                'steps' => [
                    $this->step('Maîtriser l’eau et l’hygiène', [
                        'Utiliser une eau propre et non stagnante.',
                        'Maintenir une humidité constante.',
                    ]),
                    $this->step('Récolter jeunes', [
                        'Récolter avant la floraison.',
                        'Trier et conserver au frais.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Brèdes mafana'],
                'fields' => [
                    'planting_material' => 'Semences ou plants selon la pratique locale.',
                    'maintenance' => 'Favoriser la repousse par une récolte maîtrisée et un entretien régulier.',
                    'cycle_duration' => 'Environ 25 à 40 jours pour une récolte de feuilles.',
                    'harvest' => 'Récolter les feuilles ou jeunes pousses, avec possibilité de repousse.',
                    'storage' => 'Conserver au frais et à humidité élevée.',
                ],
                'steps' => [
                    $this->step('Organiser les récoltes successives', [
                        'Récolter sans détruire le pied si une repousse est attendue.',
                        'Entretenir la fertilité et l’humidité après chaque coupe.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Taro'],
                'fields' => [
                    'planting_material' => 'Cormes, rejets ou matériel végétatif sain.',
                    'nursery' => 'Non : plantation du matériel végétatif.',
                    'sowing' => 'Planter à la profondeur et à l’espacement recommandés.',
                    'irrigation' => 'Maintenir une humidité adaptée à l’espèce, sans asphyxier le sol.',
                    'cycle_duration' => 'Environ 6 à 9 mois selon la variété et la région.',
                    'harvest' => 'Récolter à maturité, en limitant les blessures.',
                    'storage' => 'Conserver les cormes au frais, au sec et à l’abri de la lumière.',
                ],
                'steps' => [
                    $this->step('Planter le matériel végétatif', [
                        'Choisir des cormes ou rejets sains.',
                        'Respecter la profondeur et l’espacement.',
                    ]),
                    $this->step('Gérer l’humidité', [
                        'Maintenir un sol frais sans excès d’eau.',
                        'Surveiller les pourritures.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Igname'],
                'fields' => [
                    'planting_material' => 'Tubercules ou fragments de tubercules sains.',
                    'nursery' => 'Non : plantation directe.',
                    'sowing' => 'Planter à la profondeur recommandée, avec un support pour les variétés grimpantes.',
                    'maintenance' => 'Installer des tuteurs et entretenir les buttes.',
                    'cycle_duration' => 'Environ 8 à 12 mois selon l’espèce et la région.',
                    'harvest' => 'Récolter à maturité, avec précaution pour ne pas blesser les tubercules.',
                    'storage' => 'Conserver dans un local frais, sec et ventilé.',
                ],
                'steps' => [
                    $this->step('Installer les supports', [
                        'Prévoir des tuteurs solides.',
                        'Conduire les tiges sans les blesser.',
                    ]),
                    $this->step('Récolter avec précaution', [
                        'Attendre la maturité.',
                        'Éviter les blessures lors de l’extraction.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Chayote', 'Chayote (christophine)'],
                'fields' => [
                    'planting_material' => 'Fruit entier ou semence selon la pratique locale.',
                    'nursery' => 'Possible avant plantation au champ.',
                    'maintenance' => 'Conduire la plante grimpante sur un support solide.',
                    'cycle_duration' => 'Environ 120 à 180 jours selon la variété et la région.',
                    'harvest' => 'Récolter les fruits au stade tendre ou à maturité selon l’usage.',
                    'storage' => 'Conserver au frais et à l’abri des chocs.',
                ],
                'steps' => [
                    $this->step('Conduire une plante grimpante', [
                        'Installer un support adapté.',
                        'Maîtriser la croissance et l’encombrement.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Asperge verte', 'Asperge blanche'],
                'fields' => [
                    'planting_material' => 'Griffes ou couronnes saines.',
                    'nursery' => 'Non : plantation des griffes.',
                    'cycle_duration' => 'Culture pérenne ; première récolte complète après 2 à 3 ans selon le mode de conduite.',
                    'harvest' => 'Récolter les turions au stade commercial, sans épuiser la plante.',
                    'storage' => 'Conserver au frais et à humidité élevée.',
                ],
                'steps' => [
                    $this->step('Installer une culture pérenne', [
                        'Préparer une parcelle durablement fertile.',
                        'Planter les griffes et laisser la culture s’installer.',
                    ]),
                    $this->step('Gérer les récoltes pluriannuelles', [
                        'Limiter les prélèvements la première année.',
                        'Entretenir la parcelle entre les cycles.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Artichaut'],
                'fields' => [
                    'planting_material' => 'Œilletons, rejets ou plants selon la pratique.',
                    'nursery' => 'Variable.',
                    'cycle_duration' => 'Culture pérenne ; récolte des capitules selon la saison.',
                    'harvest' => 'Récolter les capitules au stade commercial avant qu’ils ne s’ouvrent.',
                    'storage' => 'Conserver au frais et consommer rapidement.',
                ],
                'steps' => [
                    $this->step('Conduire une culture à cycle particulier', [
                        'Prévoir l’espace et la durée de la culture.',
                        'Entretenir les pieds entre les récoltes.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Champignon de Paris', 'Pleurote', 'Shiitaké'],
                'fields' => [
                    'overview' => 'Production de champignons sur substrat, avec une hygiène stricte et un contrôle de l’ambiance.',
                    'varieties' => 'Choisir l’espèce et la souche adaptées au substrat, au local et au marché.',
                    'climate' => 'Température, humidité et ventilation contrôlées selon l’espèce.',
                    'soil' => 'Pas de sol : substrat préparé ou acheté.',
                    'land_preparation' => 'Préparer et assainir le local de culture.',
                    'planting_material' => 'Mycélium sain et substrat adapté.',
                    'nursery' => 'Non applicable.',
                    'sowing' => 'Ensemencer le substrat avec le mycélium.',
                    'transplanting' => 'Non applicable.',
                    'irrigation' => 'Gérer l’humidité du substrat et de l’air.',
                    'fertilization' => 'Aucun engrais maraîcher : la nutrition vient du substrat.',
                    'maintenance' => 'Contrôler l’ambiance et prévenir les contaminations.',
                    'cycle_duration' => 'Variable selon l’espèce et le procédé.',
                    'harvest' => 'Récolter au stade commercial avec des instruments propres.',
                    'storage' => 'Conserver au frais et à humidité adaptée.',
                ],
                'steps' => [
                    $this->step('Préparer le substrat et le local', [
                        'Assainir le matériel et les locaux.',
                        'Choisir un substrat adapté à l’espèce.',
                    ]),
                    $this->step('Ensemencer et coloniser', [
                        'Utiliser un mycélium sain.',
                        'Maintenir les conditions de colonisation.',
                    ]),
                    $this->step('Déclencher la fructification', [
                        'Ajuster température, humidité et ventilation.',
                        'Surveiller les contaminations.',
                    ]),
                    $this->step('Récolter et conserver', [
                        'Récolter au bon stade.',
                        'Trier et conserver au frais.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Poivron', 'Poivron vert', 'Poivron rouge', 'Poivron jaune', 'Poivron doux'],
                'fields' => [
                    'nursery' => 'Oui : pépinière recommandée.',
                    'transplanting' => 'Repiquer au stade de 4 à 6 feuilles en respectant les distances.',
                    'maintenance' => 'Tuteurer si nécessaire, pailler et surveiller les fruits.',
                    'cycle_duration' => 'Environ 90 à 120 jours selon la variété et la saison.',
                    'harvest' => 'Récolter les fruits au stade commercial, verts ou colorés selon la variété.',
                    'storage' => 'Conserver au frais et à l’abri des chocs.',
                ],
                'steps' => [
                    $this->step('Conduire la pépinière et le repiquage', [
                        'Semer en pépinière abritée.',
                        'Repiquer des plants vigoureux.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Aubergine', 'Aubergine africaine'],
                'fields' => [
                    'nursery' => 'Oui : pépinière recommandée.',
                    'transplanting' => 'Repiquer au stade approprié, sans enterrer le collet.',
                    'maintenance' => 'Tuteurer, tailler les feuilles basses et pailler.',
                    'cycle_duration' => 'Environ 80 à 110 jours selon la variété et la saison.',
                    'harvest' => 'Récolter les fruits au stade commercial, avant qu’ils ne deviennent trop durs.',
                    'storage' => 'Conserver au frais et à l’abri des chocs.',
                ],
                'steps' => [
                    $this->step('Tuteurer et entretenir', [
                        'Installer les tuteurs.',
                        'Supprimer les feuilles basses et les parties malades.',
                    ]),
                ],
            ],
            [
                'aliases' => ['Piment rouge', 'Piment vert', 'Piment doux'],
                'fields' => [
                    'nursery' => 'Oui : pépinière recommandée.',
                    'transplanting' => 'Repiquer au stade de 4 à 6 feuilles.',
                    'maintenance' => 'Tuteurer si nécessaire et surveiller les fruits.',
                    'cycle_duration' => 'Environ 80 à 120 jours selon la variété et la saison.',
                    'harvest' => 'Récolter les fruits au stade souhaité, verts ou mûrs.',
                    'storage' => 'Conserver au frais, ou sécher selon l’usage.',
                ],
                'steps' => [
                    $this->step('Conduire la pépinière', [
                        'Semer en pépinière abritée.',
                        'Repiquer des plants vigoureux.',
                    ]),
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $items
     * @return array{title: string, items: list<string>}
     */
    private function step(string $title, array $items): array
    {
        return ['title' => $title, 'items' => $items];
    }

    private function sourcesText(): string
    {
        return 'Guide de culture VolyLegume, inspiré du guide technique maraîcher MINAE et des fiches techniques CENRADER. '
            .'Les durées, doses, espacements, rendements et traitements doivent être adaptés à la variété, à la région et aux recommandations locales.';
    }
}
