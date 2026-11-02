<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Contrôle de disponibilité et de santé opérationnelle.
 *
 * Les vérifications sont volontairement en lecture seule : base de données,
 * file d'attente, sauvegardes et stockage. Aucune donnée métier nominative
 * n'est exposée par l'endpoint.
 */
class HealthService
{
    /**
     * @return array{status: string, checked_at: string, checks: array<string, array<string, mixed>>}
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->database(),
            'queue' => $this->queue(),
            'backup' => $this->backup(),
            'storage' => $this->storage(),
        ];

        return [
            'status' => $this->overallStatus($checks),
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function database(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return [
                'status' => 'ok',
                'message' => 'Connexion à la base de données opérationnelle.',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'down',
                'message' => 'Base de données inaccessible.',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        try {
            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        } catch (Throwable $exception) {
            return [
                'status' => 'degraded',
                'message' => 'File d’attente illisible.',
                'error' => class_basename($exception),
            ];
        }

        $warningBacklog = (int) config('health.queue_backlog_warning', 1000);
        $warningFailed = (int) config('health.failed_jobs_warning', 1);
        $status = 'ok';
        $messages = [];

        if ($pending > $warningBacklog) {
            $status = 'degraded';
            $messages[] = "File d’attente chargée ({$pending} tâche(s)).";
        }

        if ($failed >= $warningFailed && $failed > 0) {
            $status = 'degraded';
            $messages[] = "{$failed} tâche(s) en échec.";
        }

        return [
            'status' => $status,
            'message' => $messages === [] ? 'File d’attente saine.' : implode(' ', $messages),
            'pending' => $pending,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function backup(): array
    {
        $latest = $this->latestBackupTimestamp();
        $maxAgeHours = max(1, (int) config('health.backup_max_age_hours', 48));

        if ($latest === null) {
            return [
                'status' => 'degraded',
                'message' => 'Aucune sauvegarde trouvée dans storage/backups.',
                'last_backup_at' => null,
                'age_hours' => null,
            ];
        }

        $ageHours = round((time() - $latest) / 3600, 1);

        return [
            'status' => $ageHours > $maxAgeHours ? 'degraded' : 'ok',
            'message' => $ageHours > $maxAgeHours
                ? "Dernière sauvegarde vieille de {$ageHours} h (seuil {$maxAgeHours} h)."
                : 'Sauvegarde récente disponible.',
            'last_backup_at' => now()->setTimestamp($latest)->toIso8601String(),
            'age_hours' => $ageHours,
            'max_age_hours' => $maxAgeHours,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storage(): array
    {
        $paths = [
            'storage' => storage_path(),
            'public' => storage_path('app/public'),
            'cache' => storage_path('framework/cache'),
        ];

        $missing = [];
        $notWritable = [];

        foreach ($paths as $label => $path) {
            if (! is_dir($path)) {
                $missing[] = $label;

                continue;
            }

            if (! is_writable($path)) {
                $notWritable[] = $label;
            }
        }

        if ($missing !== [] || $notWritable !== []) {
            return [
                'status' => 'down',
                'message' => 'Stockage non accessible en écriture.',
                'missing' => $missing,
                'not_writable' => $notWritable,
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Stockage accessible en écriture.',
        ];
    }

    private function latestBackupTimestamp(): ?int
    {
        $directory = storage_path('backups');

        if (! is_dir($directory)) {
            return null;
        }

        $latest = null;

        foreach (new \DirectoryIterator($directory) as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            if (! $entry->isDir() && $entry->getExtension() !== 'zip') {
                continue;
            }

            $timestamp = $entry->getMTime();

            if ($latest === null || $timestamp > $latest) {
                $latest = $timestamp;
            }
        }

        return $latest;
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     */
    private function overallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array('down', $statuses, true)) {
            return 'down';
        }

        if (in_array('degraded', $statuses, true)) {
            return 'degraded';
        }

        return 'ok';
    }
}
