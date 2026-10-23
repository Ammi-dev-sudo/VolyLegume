<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\Harvest;
use App\Models\User;
use App\Services\ProduceStockService;
use Illuminate\Console\Command;

class BackfillProduceLots extends Command
{
    protected $signature = 'produce:backfill-lots {--farm= : Identifiant de l’exploitation à traiter}';

    protected $description = 'Crée les lots de stock manquants pour les récoltes existantes.';

    public function handle(ProduceStockService $stock): int
    {
        $farmId = $this->option('farm');

        if ($farmId !== null && ! Farm::query()->whereKey($farmId)->exists()) {
            $this->error('Exploitation introuvable.');

            return self::FAILURE;
        }

        $created = 0;

        Harvest::query()
            ->when($farmId !== null, fn ($query) => $query->where('farm_id', $farmId))
            ->whereDoesntHave('produceLot')
            ->with(['campaign.crop', 'recordedBy', 'farm'])
            ->orderBy('id')
            ->each(function (Harvest $harvest) use ($stock, &$created): void {
                $actor = $harvest->recordedBy
                    ?? $harvest->farm->manager
                    ?? User::query()->find($harvest->farm->manager_id);

                if ($actor === null) {
                    $this->warn('Récolte #'.$harvest->id.' ignorée : aucun utilisateur disponible.');

                    return;
                }

                $stock->createLotFromHarvest($harvest, $actor);
                $created++;
            });

        $this->info($created.' lot(s) de stock créé(s).');

        return self::SUCCESS;
    }
}
