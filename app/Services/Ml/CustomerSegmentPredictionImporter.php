<?php

namespace App\Services\Ml;

use App\Models\Customer;
use App\Models\Prediction;
use RuntimeException;

/**
 * Importe les segments RFM produits par le modèle Python.
 */
class CustomerSegmentPredictionImporter implements MlPredictionImporter
{
    /**
     * @param  array<string, mixed>  $modelMeta
     */
    public function import(string $csvPath, array $modelMeta = []): int
    {
        if (! is_file($csvPath)) {
            throw new RuntimeException("Fichier de segments introuvable : {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Impossible de lire le fichier de segments : {$csvPath}");
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

        $customerIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['sample_id'] ?? 0),
            $rows,
        )));

        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->get()
            ->keyBy('id');

        $imported = 0;
        $importedIds = [];

        foreach ($rows as $row) {
            $customer = $customers->get((int) ($row['sample_id'] ?? 0));

            if ($customer === null) {
                continue;
            }

            $segment = trim((string) ($row['segment'] ?? '')) ?: 'Clients occasionnels';

            Prediction::query()->updateOrCreate(
                [
                    'model' => 'customer_segment',
                    'subject_type' => Customer::class,
                    'subject_id' => $customer->id,
                ],
                [
                    'farm_id' => $customer->farm_id,
                    'label' => $segment,
                    'value' => (float) ($row['value_score'] ?? 0),
                    'score' => null,
                    'context' => [
                        'model_version' => $modelMeta['version'] ?? null,
                        'algorithm' => $modelMeta['algorithm'] ?? null,
                        'cluster' => (int) ($row['cluster'] ?? 0),
                        'segment' => $segment,
                        'recency_days' => (int) ($row['recency_days'] ?? 0),
                        'frequency' => (int) ($row['frequency'] ?? 0),
                        'monetary' => (float) ($row['monetary'] ?? 0),
                        'metrics' => $modelMeta['metrics'] ?? null,
                    ],
                    'generated_at' => now(),
                ],
            );

            $importedIds[] = $customer->id;
            $imported++;
        }

        Prediction::query()
            ->where('model', 'customer_segment')
            ->where('subject_type', Customer::class)
            ->whereNotIn('subject_id', $importedIds)
            ->delete();

        return $imported;
    }
}
