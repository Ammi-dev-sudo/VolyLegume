<?php

namespace Database\Seeders;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Campaign;
use App\Models\CropIssue;
use App\Models\Farm;
use App\Models\IrrigationEvent;
use App\Support\DashboardCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class OperationalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $farm = Farm::query()->where('name', 'Ferme Ambohipo')->first();

        if ($farm !== null) {
            $this->seedForFarm($farm);
        }
    }

    public function seedForFarm(Farm $farm): bool
    {
        $campaigns = Campaign::query()
            ->forFarm($farm->id)
            ->with('productionUnit')
            ->orderBy('id')
            ->get();
        $manager = $farm->manager ?? $farm->users()->first();

        if ($campaigns->isEmpty() || $manager === null) {
            return false;
        }

        $this->seedIrrigation($farm, $campaigns, $manager->id);
        $this->seedIssues($farm, $campaigns, $manager->id);
        DashboardCache::forget($farm);

        return true;
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     */
    private function seedIrrigation(Farm $farm, Collection $campaigns, int $managerId): void
    {
        $eventCount = 42;
        $eventsPerCampaign = (int) ceil($eventCount / $campaigns->count());
        $quotaMultipliers = [0.38, 0.82, 1.12];
        $eventVariations = [0.78, 1.00, 1.22];
        $methods = ['Goutte-à-goutte', 'Aspersion', 'Arrosage manuel', 'Gravitaire'];
        $sources = ['Réservoir', 'Puits', 'Forage', 'Récupération de pluie'];

        for ($index = 0; $index < $eventCount; $index++) {
            $campaignIndex = $index % $campaigns->count();
            $campaign = $campaigns[$campaignIndex];
            $quota = (float) $campaign->water_quota_m3;
            $targetVolume = $quota > 0
                ? $quota * $quotaMultipliers[$campaignIndex % count($quotaMultipliers)]
                : max(8, (float) $campaign->cultivated_area_m2 * 0.015);
            $volume = max(0.15, ($targetVolume / $eventsPerCampaign) * $eventVariations[$index % count($eventVariations)]);
            $tag = sprintf('Donnée démo VolyLegume : irrigation %02d', $index + 1);

            IrrigationEvent::query()->firstOrCreate(
                ['farm_id' => $farm->id, 'notes' => $tag],
                [
                    'production_unit_id' => $campaign->production_unit_id,
                    'campaign_id' => $campaign->id,
                    'irrigated_at' => today()->subDays($eventCount - $index - 1),
                    'duration_hours' => round(0.45 + (($index * 7) % 150) / 100, 2),
                    'volume_m3' => round($volume, 3),
                    'method' => $methods[$index % count($methods)],
                    'source' => $sources[$index % count($sources)],
                    'recorded_by' => $managerId,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     */
    private function seedIssues(Farm $farm, Collection $campaigns, int $managerId): void
    {
        $definitions = [
            [IssueType::Irrigation, 'Débit irrégulier en bout de ligne', IssueSeverity::Moderate, IssueStatus::InProgress, 18, 'Pression à contrôler sur la dernière rangée.'],
            [IssueType::Pest, 'Insectes observés sur jeunes feuilles', IssueSeverity::High, IssueStatus::Open, 6, 'Présence localisée, évolution à suivre.'],
            [IssueType::NutrientDeficiency, 'Feuillage pâle sur une zone', IssueSeverity::Low, IssueStatus::Monitoring, 12, 'Observation consignée pour le prochain passage.'],
            [IssueType::ExcessWater, 'Sol saturé après forte pluie', IssueSeverity::High, IssueStatus::Resolved, 24, 'Écoulement revenu à la normale lors du dernier contrôle.'],
            [IssueType::Disease, 'Taches foliaires à confirmer', IssueSeverity::Moderate, IssueStatus::Monitoring, 9, 'Quelques plants concernés, contrôle planifié.'],
            [IssueType::Drought, 'Dessèchement en bordure de parcelle', IssueSeverity::Critical, IssueStatus::InProgress, 32, 'Priorité élevée, zone à réévaluer rapidement.'],
            [IssueType::PoorGrowth, 'Croissance inégale sur deux rangs', IssueSeverity::Low, IssueStatus::Resolved, 14, 'Évolution redevenue homogène au dernier relevé.'],
            [IssueType::SoilDegradation, 'Ruissellement visible après arrosage', IssueSeverity::High, IssueStatus::Open, 21, 'Phénomène observé sur une portion de la parcelle.'],
            [IssueType::Fungus, 'Dépôt suspect sur feuilles basses', IssueSeverity::Moderate, IssueStatus::Open, 7, 'Diagnostic à confirmer avant toute action.'],
            [IssueType::Irrigation, 'Émetteur bouché sur une ligne', IssueSeverity::High, IssueStatus::Resolved, 3, 'Débit rétabli après vérification de la ligne.'],
        ];

        foreach ($definitions as $index => [$type, $title, $severity, $status, $area, $description]) {
            $campaign = $campaigns[$index % $campaigns->count()];
            $resolvedAt = $status === IssueStatus::Resolved ? now()->subDays(2) : null;

            CropIssue::query()->firstOrCreate(
                ['farm_id' => $farm->id, 'reference' => sprintf('PB-DEMO-%03d', $index + 1)],
                [
                    'production_unit_id' => $campaign->production_unit_id,
                    'campaign_id' => $campaign->id,
                    'type' => $type,
                    'title' => $title.' — démo',
                    'description' => $description.' Donnée de démonstration.',
                    'observed_at' => today()->subDays(($index * 3) % 28),
                    'severity' => $severity,
                    'affected_area_m2' => min((float) $area, (float) $campaign->cultivated_area_m2),
                    'status' => $status,
                    'recommended_actions' => 'Vérifier la situation sur le terrain et consigner le suivi.',
                    'resolution_notes' => $status === IssueStatus::Resolved ? 'Résolution consignée dans les données de démonstration.' : null,
                    'resolved_at' => $resolvedAt,
                    'reported_by' => $managerId,
                ],
            );
        }
    }
}
