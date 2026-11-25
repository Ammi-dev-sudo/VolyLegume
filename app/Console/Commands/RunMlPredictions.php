<?php

namespace App\Console\Commands;

use App\Services\Ml\CsvDatasetWriter;
use App\Services\Ml\MlUseCaseRegistry;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Génère et importe les prédictions d'un cas d'usage ML.
 */
class RunMlPredictions extends Command
{
    protected $signature = 'ml:predict
        {useCase=yield : Cas d’usage (yield, activity-risk, customer-segment)}
        {--model= : Chemin du modèle JSON entraîné}
        {--output= : Chemin CSV des prédictions}';

    protected $description = 'Génère les prédictions d’un cas d’usage et importe les résultats';

    public function handle(MlUseCaseRegistry $registry, CsvDatasetWriter $writer): int
    {
        $useCase = (string) $this->argument('useCase');
        $builder = $registry->builder($useCase);
        $runner = $registry->runner($useCase);
        $importer = $registry->importer($useCase);

        if ($builder === null || $runner === null || $importer === null) {
            $this->error('Cas d’usage ML non pris en charge : '.$useCase);
            $this->line('Cas disponibles : '.implode(', ', $registry->supportedUseCases()));

            return self::FAILURE;
        }

        $base = $registry->artifactBase($useCase);
        $model = (string) ($this->option('model') ?: config('ml.storage_path')."/{$base}_model.json");

        if (! is_file($model)) {
            $this->error("Modèle introuvable : {$model}. Lancez d’abord « php artisan ml:train {$useCase} ».");

            return self::FAILURE;
        }

        $rows = $builder->predictionRows();

        if ($rows === []) {
            $this->warn('Aucune ligne à prédire.');

            return self::SUCCESS;
        }

        $featuresPath = config('ml.storage_path')."/{$base}_features.csv";
        $outputPath = (string) ($this->option('output') ?: config('ml.storage_path')."/{$base}_predictions.csv");

        $writer->write($featuresPath, $builder->predictionHeader(), $rows);

        try {
            $runner->predict($model, $featuresPath, $outputPath);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $meta = json_decode((string) file_get_contents($model), true);
        $meta = is_array($meta) ? $meta : [];

        $imported = $importer->import($outputPath, $meta);

        $this->info(sprintf('%d résultat(s) importé(s) pour %d ligne(s).', $imported, count($rows)));

        return self::SUCCESS;
    }
}
