<?php

namespace App\Console\Commands;

use App\Models\CropIssue;
use App\Models\Farm;
use App\Models\IrrigationEvent;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateOperationalDemoData extends Command
{
    protected $signature = 'demo:populate-operations {farm : Nom exact de la ferme Ambohipo} {--force : Confirmer l’ajout de données de démonstration en production}';

    protected $description = 'Ajoute des arrosages et problèmes de démonstration à Ferme Ambohipo';

    public function handle(OperationalDemoSeeder $seeder): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->components->error('En production, relancez avec --force après avoir vérifié la ferme ciblée.');

            return self::FAILURE;
        }

        if ($this->argument('farm') !== 'Ferme Ambohipo') {
            $this->components->error('Cette commande ne cible que « Ferme Ambohipo ».');

            return self::FAILURE;
        }

        $farm = Farm::query()->where('name', 'Ferme Ambohipo')->first();

        if ($farm === null) {
            $this->components->error('Ferme Ambohipo est introuvable dans cette base.');

            return self::FAILURE;
        }

        $irrigationBefore = IrrigationEvent::query()
            ->where('farm_id', $farm->id)
            ->where('notes', 'like', 'Donnée démo VolyLegume : irrigation %')
            ->count();
        $issuesBefore = CropIssue::query()
            ->where('farm_id', $farm->id)
            ->where('reference', 'like', 'PB-DEMO-%')
            ->count();

        $seeded = DB::transaction(fn (): bool => $seeder->seedForFarm($farm));

        if (! $seeded) {
            $this->components->error('Aucune campagne ou aucun responsable disponible pour cette ferme.');

            return self::FAILURE;
        }

        $irrigationAdded = IrrigationEvent::query()
            ->where('farm_id', $farm->id)
            ->where('notes', 'like', 'Donnée démo VolyLegume : irrigation %')
            ->count() - $irrigationBefore;
        $issuesAdded = CropIssue::query()
            ->where('farm_id', $farm->id)
            ->where('reference', 'like', 'PB-DEMO-%')
            ->count() - $issuesBefore;

        $this->components->info("{$farm->name} : {$irrigationAdded} arrosage(s) et {$issuesAdded} problème(s) de démonstration ajoutés.");

        return self::SUCCESS;
    }
}
