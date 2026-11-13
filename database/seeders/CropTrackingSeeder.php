<?php

namespace Database\Seeders;

use App\Enums\CropStage;
use App\Enums\IssuePhotoKind;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Models\Campaign;
use App\Models\CampaignStageEvent;
use App\Models\CropIssue;
use App\Models\Farm;
use App\Models\ProductionUnitPhoto;
use App\Models\ProductionUnitZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Données de démonstration du suivi cultural : zones, étapes, photos et
 * problèmes. Les libellés portent explicitement la mention « démo » afin de
 * rester identifiables dans l'interface et les exports.
 */
class CropTrackingSeeder extends Seeder
{
    public function run(): void
    {
        $farm = Farm::query()->where('name', 'Ferme Ambohipo')->first();

        if ($farm === null) {
            return;
        }

        $campaign = Campaign::query()
            ->where('farm_id', $farm->id)
            ->where('name', 'like', 'Tomate P01%')
            ->first();

        if ($campaign === null) {
            return;
        }

        $unit = $campaign->productionUnit;
        $manager = $farm->users()->wherePivot('role', 'responsable')->first()
            ?? $farm->users()->first();

        if ($unit === null || $manager === null) {
            return;
        }

        $zone = ProductionUnitZone::firstOrCreate(
            ['farm_id' => $farm->id, 'production_unit_id' => $unit->id, 'code' => 'ZN'],
            [
                'name' => 'Zone nord — démo',
                'area_m2' => 600,
                'soil_type' => 'Limon argileux',
                'irrigation_type' => 'Goutte-à-goutte',
                'notes' => 'Zone de démonstration pour le rattachement des campagnes.',
                'is_active' => true,
            ],
        );

        if ($campaign->production_unit_zone_id === null) {
            $campaign->update(['production_unit_zone_id' => $zone->id]);
        }

        $stages = [
            [CropStage::SoilPreparation, now()->subDays(40), 'Labour et amendement organique (démo).'],
            [CropStage::Sowing, now()->subDays(37), 'Semis en pépinière (démo).'],
            [CropStage::Germination, now()->subDays(33), 'Levée homogène observée (démo).'],
            [CropStage::Growth, now()->subDays(20), 'Croissance végétative active (démo).'],
        ];

        foreach ($stages as [$stage, $date, $notes]) {
            CampaignStageEvent::firstOrCreate(
                [
                    'farm_id' => $farm->id,
                    'campaign_id' => $campaign->id,
                    'stage' => $stage->value,
                ],
                [
                    'recorded_at' => $date->copy()->setTime(8, 30),
                    'notes' => $notes,
                    'recorded_by' => $manager->id,
                ],
            );
        }

        $demoPath = $this->demoPhoto('campaign-photos/demo-croissance.png');

        if ($demoPath !== null && ! ProductionUnitPhoto::query()
            ->where('farm_id', $farm->id)
            ->where('campaign_id', $campaign->id)
            ->exists()) {
            ProductionUnitPhoto::create([
                'farm_id' => $farm->id,
                'production_unit_id' => $unit->id,
                'campaign_id' => $campaign->id,
                'photo_path' => $demoPath,
                'caption' => 'Suivi de croissance — démo',
                'growth_stage' => CropStage::Growth,
                'observation_type' => 'growth',
                'taken_at' => now()->subDays(20),
                'uploaded_by' => $manager->id,
            ]);
        }

        $issue = CropIssue::firstOrCreate(
            [
                'farm_id' => $farm->id,
                'reference' => 'PB-'.now()->subDays(8)->format('Ymd').'-900',
            ],
            [
                'production_unit_id' => $unit->id,
                'campaign_id' => $campaign->id,
                'type' => IssueType::Disease,
                'title' => 'Mildiou sur feuilles basses — démo',
                'description' => 'Taches brunes et duvet blanchâtre observés sur les feuilles basses après une période humide.',
                'observed_at' => now()->subDays(8)->toDateString(),
                'severity' => IssueSeverity::High,
                'affected_area_m2' => 45,
                'status' => IssueStatus::Monitoring,
                'recommended_actions' => 'Retirer les feuilles atteintes et appliquer un fongicide autorisé.',
                'reported_by' => $manager->id,
                'assigned_to' => $farm->users()->wherePivot('role', 'agent')->first()?->id,
            ],
        );

        $beforePath = $this->demoPhoto('issue-photos/demo-avant.png');
        $afterPath = $this->demoPhoto('issue-photos/demo-apres.png');

        if ($issue->photos()->count() === 0) {
            foreach ([[IssuePhotoKind::Before, $beforePath, 'Constat initial — démo'], [IssuePhotoKind::After, $afterPath, 'Après intervention — démo']] as [$kind, $path, $caption]) {
                if ($path === null) {
                    continue;
                }

                $issue->photos()->create([
                    'farm_id' => $farm->id,
                    'photo_path' => $path,
                    'kind' => $kind,
                    'caption' => $caption,
                    'taken_at' => $kind === IssuePhotoKind::Before ? now()->subDays(8) : now()->subDays(2),
                    'uploaded_by' => $manager->id,
                ]);
            }
        }
    }

    /**
     * Copie une image fournie avec l'application vers le disque média.
     */
    private function demoPhoto(string $path): ?string
    {
        $source = public_path('images/logo-sm.png');

        if (! is_file($source)) {
            return null;
        }

        $disk = Storage::disk((string) config('filesystems.media_disk', 'public'));
        $contents = file_get_contents($source);

        if ($contents === false) {
            return null;
        }

        $disk->put($path, $contents, ['visibility' => 'public']);

        return $path;
    }
}
