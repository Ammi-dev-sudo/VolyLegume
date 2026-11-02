<?php

namespace App\Console\Commands;

use App\Services\HealthService;
use Illuminate\Console\Command;

/**
 * Contrôle de santé exploitable par une tâche planifiée ou une sonde shell.
 *
 * Le code de sortie vaut 1 uniquement lorsque le service est indisponible ;
 * un état dégradé (file chargée, sauvegarde ancienne) reste un succès pour ne
 * pas couper le service, mais il est signalé en rouge dans la sortie.
 */
class SystemHealth extends Command
{
    protected $signature = 'system:health {--json : Afficher le résultat en JSON}';

    protected $description = 'Vérifie la base, la file d’attente, les sauvegardes et le stockage';

    public function handle(HealthService $health): int
    {
        $result = $health->check();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $result['status'] === 'down' ? self::FAILURE : self::SUCCESS;
        }

        $this->line('État général : '.$result['status']);

        $this->table(
            ['Contrôle', 'État', 'Détail'],
            collect($result['checks'])
                ->map(fn (array $check, string $name): array => [
                    $name,
                    $check['status'],
                    $check['message'] ?? '',
                ])
                ->values()
                ->all(),
        );

        return $result['status'] === 'down' ? self::FAILURE : self::SUCCESS;
    }
}
