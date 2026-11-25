<?php

namespace App\Console\Commands;

use App\Services\Ml\CsvDatasetWriter;
use App\Services\Ml\MlUseCaseRegistry;
use Illuminate\Console\Command;

/**
 * Exporte le jeu de données d'entraînement d'un cas d'usage ML.
 */
class ExportMlDataset extends Command
{
    protected $signature = 'ml:export
        {useCase=yield : Cas d’usage (yield, activity-risk, customer-segment)}
        {--path= : Chemin CSV de sortie}';

    protected $description = 'Exporte le jeu de données CSV d’un cas d’usage ML';

    public function handle(MlUseCaseRegistry $registry, CsvDatasetWriter $writer): int
    {
        $useCase = (string) $this->argument('useCase');
        $builder = $registry->builder($useCase);

        if ($builder === null) {
            $this->error('Cas d’usage ML non pris en charge : '.$useCase);
            $this->line('Cas disponibles : '.implode(', ', $registry->supportedUseCases()));

            return self::FAILURE;
        }

        $base = $registry->artifactBase($useCase);
        $path = (string) ($this->option('path') ?: config('ml.storage_path')."/{$base}_dataset.csv");
        $rows = $builder->trainingRows();

        if ($rows === []) {
            $this->warn('Aucune ligne exploitable : le jeu de données sera vide.');
        }

        $written = $writer->write($path, $builder->trainingHeader(), $rows);

        $this->info(sprintf('%d ligne(s) exportée(s) vers %s.', $written, $path));

        return self::SUCCESS;
    }
}
