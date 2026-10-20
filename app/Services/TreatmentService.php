<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Campaign;
use App\Models\Input;
use App\Models\PhytosanitaryTreatment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Enregistrement atomique d'un traitement phytosanitaire.
 *
 * RG07 : l'activité, le mouvement de stock et le traitement sont créés dans une
 * même transaction ; toute erreur annule l'ensemble. RG08 : la borne de récolte
 * est calculée côté serveur (applied_at + délai × 24 h).
 */
class TreatmentService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array{
     *     quantity: float|int|string,
     *     dose_value: float|int|string,
     *     dose_unit: string,
     *     pre_harvest_days: int|string,
     *     applied_at: string,
     *     delay_source?: ?string,
     *     notes?: ?string
     * }  $data
     */
    public function record(Campaign $campaign, Input $input, User $actor, array $data): PhytosanitaryTreatment
    {
        $appliedAt = Carbon::parse($data['applied_at']);
        $preHarvestDays = (int) $data['pre_harvest_days'];
        $quantity = (float) $data['quantity'];

        if ($quantity <= 0) {
            throw new InvalidArgumentException('La quantité consommée doit être strictement positive.');
        }

        if ($preHarvestDays < 0) {
            throw new InvalidArgumentException('Le délai avant récolte ne peut pas être négatif.');
        }

        $treatment = DB::transaction(function () use ($campaign, $input, $actor, $data, $appliedAt, $preHarvestDays, $quantity) {
            $lockedCampaign = Campaign::query()->whereKey($campaign->getKey())->lockForUpdate()->firstOrFail();
            $lockedInput = Input::query()->whereKey($input->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $lockedInput->farm_id !== (int) $lockedCampaign->farm_id) {
                throw new InvalidArgumentException('L’intrant et la campagne n’appartiennent pas à la même exploitation.');
            }

            if (! $lockedInput->isUsablePhytosanitary()) {
                throw new InvalidArgumentException('Ce produit n’est pas un produit phytosanitaire utilisable avec un délai avant récolte renseigné.');
            }

            /*
             * Convention de prototype : borne = instant d'application + délai × 24 h.
             */
            $minimumHarvestAt = $appliedAt->copy()->addSeconds($preHarvestDays * 86400);

            $activity = $lockedCampaign->activities()->create([
                'farm_id' => $lockedCampaign->farm_id,
                'type' => ActivityType::Treatment,
                'title' => 'Traitement — '.$lockedInput->name,
                'planned_date' => $appliedAt->toDateString(),
                'performed_at' => $appliedAt,
                'status' => ActivityStatus::Completed,
                'created_by' => $actor->id,
                'performed_by' => $actor->id,
            ]);

            $movement = $this->stock->consume(
                $lockedInput,
                $quantity,
                $actor,
                'Consommation traitement — '.$lockedCampaign->name,
                $lockedCampaign,
                $activity,
                $appliedAt,
            );

            return $lockedCampaign->treatments()->create([
                'farm_id' => $lockedCampaign->farm_id,
                'input_id' => $lockedInput->id,
                'activity_id' => $activity->id,
                'stock_movement_id' => $movement->id,
                'applied_at' => $appliedAt,
                'dose_value' => (float) $data['dose_value'],
                'dose_unit' => $data['dose_unit'],
                'pre_harvest_days' => $preHarvestDays,
                'delay_source_snapshot' => $data['delay_source'] ?? $lockedInput->delay_source,
                'minimum_harvest_at' => $minimumHarvestAt,
                'applied_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return $treatment;
    }

    /**
     * Borne de récolte applicable : la plus tardive des bornes des traitements
     * appliqués au plus tard à l'instant donné (RG09).
     */
    public function minimumHarvestAt(Campaign $campaign, CarbonInterface $at): ?Carbon
    {
        $latest = $campaign->treatments()
            ->where('applied_at', '<=', $at)
            ->max('minimum_harvest_at');

        return $latest !== null ? Carbon::parse($latest) : null;
    }
}
