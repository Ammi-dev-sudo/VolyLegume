<?php

namespace App\Services\Ml;

use App\Models\Campaign;
use App\Models\Prediction;
use RuntimeException;

/**
 * Importe les prévisions de rendement produites par le modèle Python.
 */
class YieldPredictionImporter implements MlPredictionImporter
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

        $campaignIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['sample_id'] ?? 0),
            $rows,
        )));

        $campaigns = Campaign::query()
            ->with('productionUnit')
            ->whereIn('id', $campaignIds)
            ->get()
            ->keyBy('id');

        $imported = 0;
        $importedIds = [];

        foreach ($rows as $row) {
            $campaign = $campaigns->get((int) ($row['sample_id'] ?? 0));

            if ($campaign === null) {
                continue;
            }

            $value = (float) ($row['prediction'] ?? 0);
            $score = ($row['score'] ?? '') === '' ? null : (float) $row['score'];
            $area = (float) ($campaign->cultivated_area_m2 ?? $campaign->productionUnit?->area_m2 ?? 0);

            Prediction::query()->updateOrCreate(
                [
                    'model' => 'yield',
                    'subject_type' => Campaign::class,
                    'subject_id' => $campaign->id,
                ],
                [
                    'farm_id' => $campaign->farm_id,
                    'label' => 'Rendement prévu',
                    'value' => $value,
                    'score' => $score,
                    'context' => [
                        'model_version' => $modelMeta['version'] ?? null,
                        'algorithm' => $modelMeta['algorithm'] ?? null,
                        'predicted_total_kg' => round($value * $area, 3),
                        'area_m2' => $area,
                        'metrics' => $modelMeta['metrics'] ?? null,
                    ],
                    'generated_at' => now(),
                ],
            );

            $importedIds[] = $campaign->id;
            $imported++;
        }

        Prediction::query()
            ->where('model', 'yield')
            ->where('subject_type', Campaign::class)
            ->whereNotIn('subject_id', $importedIds)
            ->delete();

        return $imported;
    }
}
