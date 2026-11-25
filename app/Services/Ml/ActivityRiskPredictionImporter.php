<?php

namespace App\Services\Ml;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Prediction;
use RuntimeException;

/**
 * Importe les probabilités de retard produites par le modèle Python.
 */
class ActivityRiskPredictionImporter implements MlPredictionImporter
{
    /**
     * @param  array<string, mixed>  $modelMeta
     */
    public function import(string $csvPath, array $modelMeta = []): int
    {
        if (! is_file($csvPath)) {
            throw new RuntimeException("Fichier de prévisions introuvable : {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Impossible de lire le fichier de prévisions : {$csvPath}");
        }

        $header = fgetcsv($handle, null, ',', '"', '');

        if ($header === false) {
            fclose($handle);

            return 0;
        }

        $header = array_map(static fn ($column): string => trim((string) $column), $header);
        $rows = [];

        while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if (count($values) !== count($header)) {
                continue;
            }

            $rows[] = array_combine($header, $values);
        }

        fclose($handle);

        $activityIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['sample_id'] ?? 0),
            $rows,
        )));

        $activities = Activity::query()
            ->with('campaign')
            ->whereIn('id', $activityIds)
            ->get()
            ->keyBy('id');

        $imported = 0;
        $importedIds = [];

        foreach ($rows as $row) {
            $activity = $activities->get((int) ($row['sample_id'] ?? 0));

            if ($activity === null) {
                continue;
            }

            $probability = max(0.0, min(1.0, (float) ($row['prediction'] ?? 0)));
            $riskLevel = $probability >= 0.66 ? 'eleve' : ($probability >= 0.33 ? 'modere' : 'faible');
            $plannedDate = $activity->planned_date;

            Prediction::query()->updateOrCreate(
                [
                    'model' => 'activity_risk',
                    'subject_type' => Activity::class,
                    'subject_id' => $activity->id,
                ],
                [
                    'farm_id' => $activity->farm_id,
                    'label' => 'Risque de retard',
                    'value' => $probability,
                    'score' => $probability,
                    'context' => [
                        'model_version' => $modelMeta['version'] ?? null,
                        'algorithm' => $modelMeta['algorithm'] ?? null,
                        'risk_level' => $riskLevel,
                        'probability' => $probability,
                        'activity_title' => $activity->title,
                        'activity_type' => $activity->type instanceof ActivityType ? $activity->type->label() : 'Inconnu',
                        'campaign_name' => $activity->campaign?->name,
                        'planned_date' => $plannedDate?->toDateString(),
                        'days_until_due' => $plannedDate !== null
                            ? (int) round(today()->startOfDay()->diffInDays($plannedDate->copy()->startOfDay(), false))
                            : null,
                        'metrics' => $modelMeta['metrics'] ?? null,
                    ],
                    'generated_at' => now(),
                ],
            );

            $importedIds[] = $activity->id;
            $imported++;
        }

        Prediction::query()
            ->where('model', 'activity_risk')
            ->where('subject_type', Activity::class)
            ->whereNotIn('subject_id', $importedIds)
            ->delete();

        return $imported;
    }
}
