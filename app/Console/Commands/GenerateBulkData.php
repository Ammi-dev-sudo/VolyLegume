<?php

namespace App\Console\Commands;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\CampaignStatus;
use App\Enums\ReferenceType;
use App\Enums\StockMovementType;
use App\Models\Farm;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Génère un volume important de données métier pour éprouver la base et les
 * écrans (tableaux de bord, pagination, exports).
 *
 * Les écritures passent par des insertions en masse (DB::table()->insert) :
 * ni événements Eloquent, ni validations métier, ni journal d'audit. C'est un
 * outil de charge, pas un simulateur de production.
 */
class GenerateBulkData extends Command
{
    protected $signature = 'data:bulk
        {--farm= : Identifiant de l’exploitation (défaut : la première trouvée)}
        {--campaigns=500 : Nombre de campagnes à générer}
        {--activities=20000 : Nombre d’activités à générer}
        {--movements=50000 : Nombre de mouvements de stock à générer}
        {--chunk=2000 : Nombre de lignes par lot d’insertion}';

    protected $description = 'Génère un gros volume de données métier (campagnes, activités, mouvements de stock)';

    /**
     * Plafond du protocole PostgreSQL pour le nombre de paramètres liés.
     */
    private const MAX_BIND_PARAMETERS = 65535;

    /**
     * @var list<string>
     */
    private const ACTIVITY_TITLES = [
        'Préparation du sol',
        'Semis en pépinière',
        'Repiquage',
        'Fertilisation de fond',
        'Traitement phytosanitaire',
        'Irrigation goutte-à-goutte',
        'Désherbage manuel',
        'Observation de la parcelle',
        'Entretien des lignes',
        'Récolte partielle',
    ];

    /**
     * @var list<string>
     */
    private const MOVEMENT_REASONS = [
        'Consommation lors de l’activité',
        'Entrée fournisseur',
        'Correction d’inventaire',
        'Sortie pour essai',
        'Restock campagne',
    ];

    public function handle(): int
    {
        $farm = $this->resolveFarm();

        if ($farm === null) {
            $this->error('Aucune exploitation trouvée. Lancez `php artisan migrate:fresh --seed` d’abord.');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));

        $this->components->info(sprintf('Exploitation #%d — %s', $farm->id, $farm->name));

        $userIds = $farm->users()->wherePivot('is_active', true)->pluck('users.id')->all();

        if ($userIds === []) {
            $this->error('Cette exploitation n’a aucun compte rattaché actif.');

            return self::FAILURE;
        }

        $started = microtime(true);

        $this->enableFastLoad();

        $campaignIds = $this->generateCampaigns($farm, (int) $this->option('campaigns'), $chunk);
        $this->generateActivities($farm, $campaignIds, $userIds, (int) $this->option('activities'), $chunk);
        $this->generateStockMovements($farm, $campaignIds, $userIds, (int) $this->option('movements'), $chunk);
        $this->refreshInputQuantities($farm);

        $this->disableFastLoad();
        $this->checkForeignKeys($farm);

        $this->newLine();
        $this->components->info(sprintf('Terminé en %.1f s', microtime(true) - $started));

        return self::SUCCESS;
    }

    /**
     * Le chargement en masse désactive les déclencheurs de clés étrangères et
     * la synchronisation WAL : c'est ce qui fait passer l'import de plusieurs
     * minutes à quelques dizaines de secondes. L'intégrité est contrôlée
     * ensuite par `checkForeignKeys()`.
     */
    private function enableFastLoad(): void
    {
        try {
            DB::statement('SET session_replication_role = replica');
            DB::statement('SET synchronous_commit = off');

            $this->components->twoColumnDetail('Mode', 'chargement rapide (FK désactivées)');
        } catch (Throwable) {
            $this->components->twoColumnDetail('Mode', 'chargement standard (FK vérifiées)');
        }
    }

    private function disableFastLoad(): void
    {
        try {
            DB::statement('SET session_replication_role = origin');
            DB::statement('SET synchronous_commit = on');
        } catch (Throwable) {
            // La session sera de toute façon fermée à la fin de la commande.
        }
    }

    /**
     * Contrôle l'intégrité référentielle des lignes générées : les déclencheurs
     * de clés étrangères étaient désactivés pendant l'import.
     */
    private function checkForeignKeys(Farm $farm): void
    {
        $orphans = DB::selectOne(
            <<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM activities a
                   LEFT JOIN campaigns c ON c.id = a.campaign_id AND c.farm_id = a.farm_id
                  WHERE a.farm_id = ? AND c.id IS NULL) AS activities,
                (SELECT COUNT(*) FROM stock_movements m
                   LEFT JOIN inputs i ON i.id = m.input_id AND i.farm_id = m.farm_id
                  WHERE m.farm_id = ? AND i.id IS NULL) AS movements
            SQL,
            [$farm->id, $farm->id],
        );

        $total = (int) $orphans->activities + (int) $orphans->movements;

        if ($total === 0) {
            $this->components->twoColumnDetail('Intégrité FK', 'OK');

            return;
        }

        $this->components->twoColumnDetail('Intégrité FK', sprintf('%d ligne(s) orpheline(s)', $total));
    }

    private function resolveFarm(): ?Farm
    {
        $farmId = $this->option('farm');

        return $farmId
            ? Farm::query()->find($farmId)
            : Farm::query()->orderBy('id')->first();
    }

    /**
     * @return list<int>
     */
    private function generateCampaigns(Farm $farm, int $count, int $chunk): array
    {
        if ($count <= 0) {
            return [];
        }

        $unitIds = $farm->productionUnits()->pluck('id')->all();
        $cropIds = $farm->crops()->pluck('id')->all();

        if ($unitIds === [] || $cropIds === []) {
            $this->error('L’exploitation doit avoir au moins une parcelle et une culture.');

            return [];
        }

        $rows = [];
        $now = now();

        for ($i = 1; $i <= $count; $i++) {
            $startDate = $now->copy()->subDays(random_int(0, 900))->startOfDay();

            $rows[] = [
                'farm_id' => $farm->id,
                'production_unit_id' => $unitIds[array_rand($unitIds)],
                'crop_id' => $cropIds[array_rand($cropIds)],
                'name' => sprintf('BULK-%d campagne %d', $farm->id, $i),
                'start_date' => $startDate->toDateString(),
                'reference_type' => random_int(0, 1) === 0 ? ReferenceType::Sowing->value : ReferenceType::Planting->value,
                'expected_end_date' => $startDate->copy()->addDays(random_int(40, 140))->toDateString(),
                'actual_end_date' => null,
                'cultivated_area_m2' => random_int(80, 1200),
                'status' => $this->randomCampaignStatus()->value,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertInBatches('campaigns', $rows, $chunk);

        $this->components->twoColumnDetail('Campagnes', (string) $count);

        return DB::table('campaigns')
            ->where('name', 'like', sprintf('BULK-%d%%', $farm->id))
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<int>  $campaignIds
     * @param  list<int>  $userIds
     */
    private function generateActivities(Farm $farm, array $campaignIds, array $userIds, int $count, int $chunk): void
    {
        if ($count <= 0 || $campaignIds === []) {
            return;
        }

        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $status = $this->randomActivityStatus();
            $plannedDate = $now->copy()->subDays(random_int(0, 400))->startOfDay();
            $createdBy = $userIds[array_rand($userIds)];

            $rows[] = [
                'farm_id' => $farm->id,
                'campaign_id' => $campaignIds[array_rand($campaignIds)],
                'calendar_step_id' => null,
                'type' => $this->randomActivityType()->value,
                'title' => self::ACTIVITY_TITLES[array_rand(self::ACTIVITY_TITLES)],
                'description' => null,
                'planned_date' => $plannedDate->toDateString(),
                'performed_at' => $status === ActivityStatus::Completed
                    ? $plannedDate->copy()->addHours(random_int(6, 15))->toDateTimeString()
                    : null,
                'status' => $status->value,
                'assigned_to' => $userIds[array_rand($userIds)],
                'created_by' => $createdBy,
                'performed_by' => $status === ActivityStatus::Completed ? $createdBy : null,
                'estimated_cost' => random_int(0, 500) * 1000,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= $chunk) {
                $this->insertInBatches('activities', $rows, $chunk);
                $rows = [];
            }
        }

        $this->insertInBatches('activities', $rows, $chunk);

        $this->components->twoColumnDetail('Activités', (string) $count);
    }

    /**
     * @param  list<int>  $campaignIds
     * @param  list<int>  $userIds
     */
    private function generateStockMovements(Farm $farm, array $campaignIds, array $userIds, int $count, int $chunk): void
    {
        if ($count <= 0) {
            return;
        }

        $inputIds = $farm->inputs()->pluck('id')->all();

        if ($inputIds === []) {
            $this->error('L’exploitation doit avoir au moins un intrant.');

            return;
        }

        // Solde de départ de chaque intrant : la contrainte
        // `inputs_quantity_non_negative` interdit de descendre sous zéro.
        $balances = [];

        foreach ($farm->inputs()->get(['id', 'quantity']) as $input) {
            $balances[$input->id] = (float) $input->quantity;
        }

        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $inputId = $inputIds[array_rand($inputIds)];
            $balance = $balances[$inputId] ?? 0.0;
            $quantity = random_int(1, 40) + round(random_int(0, 99) / 100, 2);
            $type = $this->randomMovementType();

            if ($type->isIncoming()) {
                $delta = $quantity;
            } elseif ($balance < $quantity) {
                // Stock insuffisant : on enregistre une entrée plutôt que de
                // faire tomber le solde sous zéro.
                $type = StockMovementType::Entry;
                $delta = $quantity;
            } elseif ($type === StockMovementType::Adjustment) {
                $delta = random_int(0, 1) === 0 ? $quantity : -$quantity;
            } else {
                $delta = -$quantity;
            }

            $balances[$inputId] = round($balance + $delta, 3);

            $rows[] = [
                'farm_id' => $farm->id,
                'input_id' => $inputId,
                'campaign_id' => $campaignIds === [] ? null : $campaignIds[array_rand($campaignIds)],
                'activity_id' => null,
                'type' => $type->value,
                'quantity_delta' => $delta,
                'occurred_at' => $now->copy()->subDays(random_int(0, 365))->toDateTimeString(),
                'created_by' => $userIds[array_rand($userIds)],
                'reason' => self::MOVEMENT_REASONS[array_rand(self::MOVEMENT_REASONS)],
                'corrects_movement_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= $chunk) {
                $this->insertInBatches('stock_movements', $rows, $chunk);
                $rows = [];
            }
        }

        $this->insertInBatches('stock_movements', $rows, $chunk);

        $this->components->twoColumnDetail('Mouvements de stock', (string) $count);
    }

    /**
     * Insère des lignes par lots en tenant compte de la limite du protocole
     * PostgreSQL : 65 535 paramètres par requête, donc `nb lignes × nb colonnes`
     * doit rester sous ce plafond.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertInBatches(string $table, array $rows, int $chunk): void
    {
        if ($rows === []) {
            return;
        }

        $batchSize = max(1, min($chunk, intdiv(self::MAX_BIND_PARAMETERS, count($rows[0]))));

        foreach (array_chunk($rows, $batchSize) as $batch) {
            DB::table($table)->insert($batch);
        }
    }

    /**
     * Le solde d'un intrant est toujours la somme de ses variations : on
     * recalcule donc la colonne `inputs.quantity` après l'insertion en masse.
     */
    private function refreshInputQuantities(Farm $farm): void
    {
        DB::statement(
            <<<'SQL'
            UPDATE inputs AS i
            SET quantity = COALESCE(t.total, 0),
                critical_notified_at = CASE
                    WHEN COALESCE(t.total, 0) < i.minimum_stock THEN COALESCE(i.critical_notified_at, NOW())
                    ELSE NULL
                END,
                updated_at = NOW()
            FROM (
                SELECT input_id, SUM(quantity_delta) AS total
                FROM stock_movements
                WHERE farm_id = ?
                GROUP BY input_id
            ) AS t
            WHERE i.id = t.input_id AND i.farm_id = ?
            SQL,
            [$farm->id, $farm->id],
        );
    }

    private function randomCampaignStatus(): CampaignStatus
    {
        return Arr::random([
            CampaignStatus::Planned,
            CampaignStatus::Ongoing,
            CampaignStatus::Ongoing,
            CampaignStatus::Completed,
            CampaignStatus::Completed,
            CampaignStatus::Suspended,
        ]);
    }

    private function randomActivityStatus(): ActivityStatus
    {
        return Arr::random([
            ActivityStatus::Planned,
            ActivityStatus::Planned,
            ActivityStatus::Completed,
            ActivityStatus::Completed,
            ActivityStatus::Cancelled,
        ]);
    }

    private function randomActivityType(): ActivityType
    {
        return Arr::random(ActivityType::cases());
    }

    private function randomMovementType(): StockMovementType
    {
        return Arr::random([
            StockMovementType::Entry,
            StockMovementType::Consumption,
            StockMovementType::Consumption,
            StockMovementType::Exit,
            StockMovementType::Adjustment,
        ]);
    }
}
