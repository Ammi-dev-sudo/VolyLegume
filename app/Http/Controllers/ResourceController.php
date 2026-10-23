<?php

namespace App\Http\Controllers;

use App\Enums\FarmRole;
use Illuminate\View\View;

/**
 * Centre de ressources : tutoriels vidéo, guides internes et rappel des rôles.
 *
 * Les vidéos sont des recherches YouTube ciblées afin de toujours proposer un
 * lien valide, sans dépendre d'une vidéo précise qui pourrait être retirée.
 */
class ResourceController extends Controller
{
    public function index(): View
    {
        return view('resources.index', [
            'videos' => $this->videos(),
            'guides' => $this->guides(),
            'roles' => $this->roles(),
        ]);
    }

    /**
     * @return list<array{title: string, description: string, level: string, duration: string, url: string, accent: string}>
     */
    private function videos(): array
    {
        return [
            [
                'title' => 'Démarrer une exploitation maraîchère',
                'description' => 'Préparer le terrain, choisir les cultures et organiser les premières parcelles.',
                'level' => 'Débutant',
                'duration' => 'Vidéo',
                'url' => 'https://www.youtube.com/results?search_query=d%C3%A9marrer+une+exploitation+mara%C3%AEch%C3%A8re',
                'accent' => 'emerald',
            ],
            [
                'title' => 'Planifier avec un calendrier cultural',
                'description' => 'Enchaîner semis, repiquage, entretien et récolte sans trous ni retards.',
                'level' => 'Intermédiaire',
                'duration' => 'Vidéo',
                'url' => 'https://www.youtube.com/results?search_query=calendrier+cultural+mara%C3%AEchage',
                'accent' => 'lime',
            ],
            [
                'title' => 'Gérer les stocks d’intrants',
                'description' => 'Suivre les entrées et sorties, anticiper les seuils critiques et éviter les ruptures.',
                'level' => 'Débutant',
                'duration' => 'Vidéo',
                'url' => 'https://www.youtube.com/results?search_query=gestion+stock+intrants+agricoles',
                'accent' => 'amber',
            ],
            [
                'title' => 'Reconnaître les maladies et ravageurs',
                'description' => 'Observer les feuilles et fruits, identifier les symptômes et réagir au bon moment.',
                'level' => 'Intermédiaire',
                'duration' => 'Vidéo',
                'url' => 'https://www.youtube.com/results?search_query=maladies+ravageurs+tomate+mara%C3%AEchage',
                'accent' => 'rose',
            ],
            [
                'title' => 'Traitements et délais avant récolte',
                'description' => 'Appliquer un traitement raisonné et respecter le délai de sécurité avant la cueillette.',
                'level' => 'Intermédiaire',
                'duration' => 'Vidéo',
                'url' => 'https://www.youtube.com/results?search_query=traitement+phytosanitaire+d%C3%A9lai+avant+r%C3%A9colte',
                'accent' => 'sky',
            ],
            [
                'title' => 'Calculer la marge d’une campagne',
                'description' => 'Comparer dépenses et recettes pour savoir si la campagne est réellement rentable.',
                'level' => 'Avancé',
                'duration' => 'Vidéo',
                'url' => 'https://www.youtube.com/results?search_query=rentabilit%C3%A9+campagne+mara%C3%AEch%C3%A8re',
                'accent' => 'indigo',
            ],
        ];
    }

    /**
     * @return list<array{title: string, description: string, route: string, cta: string, icon: string}>
     */
    private function guides(): array
    {
        return [
            [
                'title' => 'Parcelles',
                'description' => 'Déclarez vos surfaces, leur emplacement et leur disponibilité.',
                'route' => 'production-units.index',
                'cta' => 'Ouvrir les parcelles',
                'icon' => 'plot',
            ],
            [
                'title' => 'Cultures & fiches',
                'description' => 'Créez vos cultures, consultez leurs fiches de culture et leurs étapes types.',
                'route' => 'crops.index',
                'cta' => 'Voir les cultures',
                'icon' => 'leaf',
            ],
            [
                'title' => 'Campagnes',
                'description' => 'Lancez une campagne, générez son calendrier et suivez son avancement.',
                'route' => 'campaigns.index',
                'cta' => 'Voir les campagnes',
                'icon' => 'calendar',
            ],
            [
                'title' => 'Activités',
                'description' => 'Planifiez semis, entretien et récoltes, puis marquez-les comme réalisées.',
                'route' => 'activities.index',
                'cta' => 'Ouvrir les activités',
                'icon' => 'check',
            ],
            [
                'title' => 'Intrants & stocks',
                'description' => 'Suivez les quantités, les seuils d’alerte et les mouvements de stock.',
                'route' => 'inputs.index',
                'cta' => 'Gérer les stocks',
                'icon' => 'box',
            ],
            [
                'title' => 'Récoltes & finances',
                'description' => 'Enregistrez les récoltes, les recettes et les dépenses de la campagne.',
                'route' => 'harvests.index',
                'cta' => 'Voir les récoltes',
                'icon' => 'coin',
            ],
        ];
    }

    /**
     * @return list<array{role: FarmRole, description: string, permissions: list<array{label: string, allowed: bool}>, accent: string}>
     */
    private function roles(): array
    {
        return [
            [
                'role' => FarmRole::Admin,
                'description' => 'Contrôle total de l’exploitation et des accès.',
                'accent' => 'amber',
                'permissions' => $this->permissionsFor(FarmRole::Admin),
            ],
            [
                'role' => FarmRole::Responsable,
                'description' => 'Pilote la production, les finances et l’équipe.',
                'accent' => 'emerald',
                'permissions' => $this->permissionsFor(FarmRole::Responsable),
            ],
            [
                'role' => FarmRole::Agent,
                'description' => 'Saisit les données de terrain au quotidien.',
                'accent' => 'sky',
                'permissions' => $this->permissionsFor(FarmRole::Agent),
            ],
        ];
    }

    /**
     * @return list<array{label: string, allowed: bool}>
     */
    private function permissionsFor(FarmRole $role): array
    {
        return [
            ['label' => 'Consulter le tableau de bord', 'allowed' => true],
            ['label' => 'Saisir activités, récoltes et stocks', 'allowed' => true],
            ['label' => 'Gérer les référentiels', 'allowed' => $role->canManageReferentials()],
            ['label' => 'Gérer les finances', 'allowed' => $role->canManageFinances()],
            ['label' => 'Gérer l’équipe et les rôles', 'allowed' => $role->canManageTeam()],
        ];
    }
}
