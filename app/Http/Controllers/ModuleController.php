<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ModuleController extends Controller
{
    use UsesFarmContext;

    public function index(): View
    {
        $farm = $this->farm();

        $groups = [
            [
                'title' => 'Pilotage',
                'description' => 'Les vues transversales pour décider et suivre l’exploitation.',
                'items' => [
                    [
                        'title' => 'Plan interactif',
                        'description' => 'Visualisez les parcelles, les cultures et les alertes sur un plan d’ensemble.',
                        'route' => 'farm-map.index',
                    ],
                    [
                        'title' => 'Tableau de bord',
                        'description' => 'Retrouvez les indicateurs financiers, les récoltes et les alertes.',
                        'route' => 'dashboard',
                    ],
                    [
                        'title' => 'Agenda cultural',
                        'description' => 'Consultez les activités planifiées, semaine après semaine.',
                        'route' => 'calendar.index',
                    ],
                    [
                        'title' => 'Rentabilité',
                        'description' => 'Comparez rendement, dépenses, recettes et eau par culture et par parcelle.',
                        'route' => 'profitability.index',
                    ],
                ],
            ],
            [
                'title' => 'Production',
                'description' => 'Parcelles, cultures et opérations de terrain.',
                'items' => [
                    [
                        'title' => 'Parcelles',
                        'description' => 'Gérez les surfaces, les repères et la disponibilité des parcelles.',
                        'route' => 'production-units.index',
                    ],
                    [
                        'title' => 'Cultures',
                        'description' => 'Référentiel des cultures, variétés et fiches de culture.',
                        'route' => 'crops.index',
                    ],
                    [
                        'title' => 'Campagnes',
                        'description' => 'Suivez les campagnes, leurs calendriers et leurs rendements.',
                        'route' => 'campaigns.index',
                    ],
                    [
                        'title' => 'Activités',
                        'description' => 'Planifiez et réalisez les travaux de l’exploitation.',
                        'route' => 'activities.index',
                    ],
                    [
                        'title' => 'Modèles de calendrier',
                        'description' => 'Définissez les étapes types qui composent un calendrier cultural.',
                        'route' => 'calendar-steps.index',
                    ],
                    [
                        'title' => 'Intrants & stocks',
                        'description' => 'Suivez les entrées, sorties et seuils critiques des intrants.',
                        'route' => 'inputs.index',
                    ],
                    [
                        'title' => 'Traitements',
                        'description' => 'Enregistrez les traitements et contrôlez le délai avant récolte.',
                        'route' => 'treatments.index',
                    ],
                    [
                        'title' => 'Récoltes',
                        'description' => 'Saisissez les récoltes et consultez leur traçabilité.',
                        'route' => 'harvests.index',
                    ],
                    [
                        'title' => 'Irrigation & eau',
                        'description' => 'Enregistrez les arrosages, suivez les volumes et comparez les campagnes.',
                        'route' => 'irrigation.index',
                    ],
                ],
            ],
            [
                'title' => 'Commercial & finances',
                'description' => 'Du produit récolté jusqu’à l’encaissement et à la marge.',
                'items' => [
                    [
                        'title' => 'Stock récolté',
                        'description' => 'Suivez les lots, les mouvements, les pertes et la traçabilité par QR.',
                        'route' => 'produce-lots.index',
                    ],
                    [
                        'title' => 'Clients',
                        'description' => 'Gérez les acheteurs et leurs coordonnées.',
                        'route' => 'customers.index',
                    ],
                    [
                        'title' => 'Ventes',
                        'description' => 'Créez les ventes, encaissez les paiements et annulez si nécessaire.',
                        'route' => 'sales.index',
                    ],
                    [
                        'title' => 'Dépenses',
                        'description' => 'Enregistrez les coûts de production par campagne.',
                        'route' => 'expenses.index',
                    ],
                    [
                        'title' => 'Recettes',
                        'description' => 'Suivez les recettes et les rattachements aux récoltes ou ventes.',
                        'route' => 'revenues.index',
                    ],
                ],
            ],
            [
                'title' => 'Exploitation',
                'description' => 'Comptes, rattachements et ressources de l’application.',
                'items' => [
                    [
                        'title' => 'Exploitations',
                        'description' => 'Changez d’exploitation ou créez un nouvel espace de travail.',
                        'route' => 'farms.index',
                    ],
                    [
                        'title' => 'Notifications',
                        'description' => 'Consultez les alertes de stock et les notifications internes.',
                        'route' => 'notifications.index',
                    ],
                    [
                        'title' => 'Ressources & tutoriels',
                        'description' => 'Vidéos, guides pas à pas et rappel des permissions.',
                        'route' => 'resources.index',
                    ],
                ],
            ],
        ];

        if (Gate::allows('manage-team', $farm)) {
            $groups[3]['items'][] = [
                'title' => 'Équipe',
                'description' => 'Invitez des membres et ajustez leurs rôles dans l’exploitation.',
                'route' => 'team.index',
            ];
        }

        if (Gate::allows('viewAny', AuditLog::class)) {
            $groups[3]['items'][] = [
                'title' => 'Journal d’audit',
                'description' => 'Consultez les créations, modifications et accès sensibles.',
                'route' => 'audit.index',
            ];
        }

        return view('modules.index', compact('farm', 'groups'));
    }
}
