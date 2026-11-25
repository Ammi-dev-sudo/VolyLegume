<?php

namespace App\Console\Commands;

use App\Services\Ml\MlUseCaseRegistry;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Entraîne le modèle d'un cas d'usage ML à partir du jeu de données exporté.
 */
class TrainMlModel extends Command
{
    protected $signature = 'ml:train
        {useCase=yield : Cas d’usage (yield, activity-risk, customer-segment)}
        {--dataset= : Chemin du jeu de données CSV}
        {--model= : Chemin du modèle JSON à écrire}
        {--metrics= : Chemin des métriques JSON à écrire}';

    protected $description = 'Entraîne le modèle ML d’un cas d’usage';

    public function handle(MlUseCaseRegistry $registry): int
    {
        $useCase = (string) $this->argument('useCase');
        $runner = $registry->runner($useCase);

        if ($runner === null) {
            $this->error('Cas d’usage ML non pris en charge : '.$useCase);
            $this->line('Cas disponibles : '.implode(', ', $registry->supportedUseCases()));

            return self::FAILURE;
        }

        $base = $registry->artifactBase($useCase);
        $dataset = (string) ($this->option('dataset') ?: config('ml.storage_path')."/{$base}_dataset.csv");
        $model = (string) ($this->option('model') ?: config('ml.storage_path')."/{$base}_model.json");
        $metricsPath = (string) ($this->option('metrics') ?: config('ml.storage_path')."/{$base}_metrics.json");

        if (! is_file($dataset)) {
            $this->error("Jeu de données introuvable : {$dataset}. Lancez d’abord « php artisan ml:export {$useCase} ».");

            return self::FAILURE;
        }

        try {
            $result = $runner->train($dataset, $model, $metricsPath);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Modèle entraîné.');
        $this->line($this->describeMetrics($result['metrics'] ?? []));
        $this->line("Modèle écrit dans {$model}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function describeMetrics(array $metrics): string
    {
        if (isset($metrics['accuracy'])) {
            return sprintf(
                'Accuracy %.3f · Précision %.3f · Rappel %.3f · F1 %.3f · AUC %.3f · base %.3f (%d train / %d test).',
                (float) $metrics['accuracy'],
                (float) ($metrics['precision'] ?? 0),
                (float) ($metrics['recall'] ?? 0),
                (float) ($metrics['f1'] ?? 0),
                (float) ($metrics['auc'] ?? 0),
                (float) ($metrics['base_rate'] ?? 0),
                (int) ($metrics['train_count'] ?? 0),
                (int) ($metrics['test_count'] ?? 0),
            );
        }

        if (isset($metrics['inertia'])) {
            $sizes = array_map('intval', (array) ($metrics['cluster_sizes'] ?? []));

            return sprintf(
                'K-means k=%d · inertie %.3f · tailles [%s] · %d client(s).',
                (int) ($metrics['k'] ?? 0),
                (float) $metrics['inertia'],
                implode(', ', $sizes),
                (int) ($metrics['sample_count'] ?? 0),
            );
        }

        return sprintf(
            'MAE %.4f · RMSE %.4f · R² %.4f · baseline MAE %.4f (%d train / %d test).',
            (float) ($metrics['mae'] ?? 0),
            (float) ($metrics['rmse'] ?? 0),
            (float) ($metrics['r2'] ?? 0),
            (float) ($metrics['baseline_mae'] ?? 0),
            (int) ($metrics['train_count'] ?? 0),
            (int) ($metrics['test_count'] ?? 0),
        );
    }
}
