<?php

namespace App\Services\Ml;

use App\Models\Campaign;
use App\Models\Harvest;
use Carbon\CarbonInterface;

/**
 * Construit le jeu de données de prévision du rendement (kg/m²).
 *
 * Les variables sont connues avant la récolte (surface, culture, parcelle,
 * irrigation, traitements, avancement) afin d'éviter toute fuite de données.
 * La cible est la quantité récoltée rapportée à la surface récoltée.
 */
class YieldDatasetBuilder implements MlDatasetBuilder
{
    public const ID_COLUMN = 'sample_id';

    public const TARGET_COLUMN = 'target';

    /**
     * @var list<string>
     */
    public const NUMERIC_FEATURES = [
        'cultivated_area_m2',
        'planting_density',
        'estimated_cycle_days',
        'crop_average_yield_kg_m2',
        'elapsed_days',
        'irrigation_volume_m3',
        'irrigation_hours',
        'treatment_count',
        'treatment_dose_sum',
        'activity_count',
        'parcel_area_m2',
        'start_month',
    ];

    /**
     * @var list<string>
     */
    public const CATEGORICAL_FEATURES = [
        'crop_category',
        'soil_type',
        'irrigation_type',
        'sun_exposure',
        'slope',
        'production_unit_type',
    ];

    /**
     * @return list<string>
     */
    public function features(): array
    {
        return array_merge(self::NUMERIC_FEATURES, self::CATEGORICAL_FEATURES);
    }

    /**
     * @return list<string>
     */
    public function trainingHeader(): array
    {
        return array_merge([self::ID_COLUMN], $this->features(), [self::TARGET_COLUMN]);
    }

    /**
     * @return list<string>
     */
    public function predictionHeader(): array
    {
        return array_merge([self::ID_COLUMN], $this->features());
    }

    /**
     * Une ligne par récolte exploitable (quantité et surface positives).
     *
     * @return list<array<string, mixed>>
     */
    public function trainingRows(): array
    {
        return Harvest::query()
            ->with([
                'campaign.crop.category',
                'campaign.productionUnit',
                'campaign.irrigationEvents',
                'campaign.treatments',
                'campaign.activities',
            ])
            ->whereNotNull('campaign_id')
            ->orderBy('id')
            ->get()
            ->map(function (Harvest $harvest): ?array {
                $campaign = $harvest->campaign;

                if ($campaign === null) {
                    return null;
                }

                $area = $this->harvestArea($harvest, $campaign);
                $quantity = (float) $harvest->quantity;

                if ($area <= 0.0 || $quantity <= 0.0) {
                    return null;
                }

                $row = $this->featureRow($campaign, $harvest->harvested_at);
                $row[self::ID_COLUMN] = $harvest->id;
                $row[self::TARGET_COLUMN] = $quantity / $area;

                return $row;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Une ligne par campagne active encore sans récolte.
     *
     * @return list<array<string, mixed>>
     */
    public function predictionRows(): array
    {
        return Campaign::query()
            ->with([
                'crop.category',
                'productionUnit',
                'irrigationEvents',
                'treatments',
                'activities',
            ])
            ->active()
            ->whereDoesntHave('harvests')
            ->orderBy('id')
            ->get()
            ->map(function (Campaign $campaign): array {
                $row = $this->featureRow($campaign, now());
                $row[self::ID_COLUMN] = $campaign->id;

                return $row;
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function featureRow(Campaign $campaign, ?CarbonInterface $reference): array
    {
        $unit = $campaign->productionUnit;
        $crop = $campaign->crop;
        $start = $campaign->start_date;

        $elapsedDays = 0;

        if ($start !== null && $reference !== null) {
            $elapsedDays = max(0, (int) round($start->diffInDays($reference, false)));
        }

        return [
            'cultivated_area_m2' => (float) ($campaign->cultivated_area_m2 ?? $unit?->area_m2 ?? 0),
            'planting_density' => (float) ($campaign->planting_density ?? $crop?->planting_density ?? 0),
            'estimated_cycle_days' => (int) ($crop?->estimated_cycle_days ?? 0),
            'crop_average_yield_kg_m2' => (float) ($crop?->average_yield_kg_m2 ?? 0),
            'elapsed_days' => $elapsedDays,
            'irrigation_volume_m3' => (float) $campaign->irrigationEvents->sum('volume_m3'),
            'irrigation_hours' => (float) $campaign->irrigationEvents->sum('duration_hours'),
            'treatment_count' => (int) $campaign->treatments->count(),
            'treatment_dose_sum' => (float) $campaign->treatments->sum('dose_value'),
            'activity_count' => (int) $campaign->activities->count(),
            'parcel_area_m2' => (float) ($unit?->area_m2 ?? 0),
            'start_month' => $start?->month ?? 0,
            'crop_category' => $crop?->category?->name ?? 'inconnue',
            'soil_type' => $unit?->soil_type ?: 'inconnu',
            'irrigation_type' => $unit?->irrigation_type ?: 'inconnu',
            'sun_exposure' => $unit?->sun_exposure ?: 'inconnu',
            'slope' => $unit?->slope ?: 'inconnu',
            'production_unit_type' => $unit?->type ?: 'inconnu',
        ];
    }

    private function harvestArea(Harvest $harvest, Campaign $campaign): float
    {
        $area = (float) ($harvest->harvested_area_m2 ?? 0);

        if ($area > 0.0) {
            return $area;
        }

        $area = (float) ($campaign->cultivated_area_m2 ?? 0);

        if ($area > 0.0) {
            return $area;
        }

        return (float) ($campaign->productionUnit?->area_m2 ?? 0);
    }
}
