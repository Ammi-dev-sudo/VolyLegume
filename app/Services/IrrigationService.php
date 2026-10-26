<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Farm;
use App\Models\IrrigationEvent;
use Illuminate\Support\Collection;

class IrrigationService
{
    /**
     * @return array<string, mixed>
     */
    public function summaryForCampaign(Campaign $campaign): array
    {
        $events = IrrigationEvent::query()
            ->where('campaign_id', $campaign->id)
            ->get();

        $totalVolumeM3 = (float) $events->sum(fn (IrrigationEvent $event): float => (float) $event->volume_m3);
        $totalDurationHours = (float) $events->sum(fn (IrrigationEvent $event): float => (float) $event->duration_hours);
        $areaM2 = (float) ($campaign->cultivated_area_m2 ?? $campaign->productionUnit?->area_m2 ?? 0);
        $volumePerM2 = $areaM2 > 0 ? $totalVolumeM3 / $areaM2 : null;

        $quota = $campaign->water_quota_m3 !== null ? (float) $campaign->water_quota_m3 : null;
        $thresholdPercent = $campaign->water_alert_threshold_percent !== null ? (float) $campaign->water_alert_threshold_percent : 80.0;
        $alertThresholdM3 = $quota !== null ? ($quota * $thresholdPercent / 100.0) : null;

        $status = 'ok';
        if ($quota !== null && $totalVolumeM3 >= $quota) {
            $status = 'exceeded';
        } elseif ($alertThresholdM3 !== null && $totalVolumeM3 >= $alertThresholdM3) {
            $status = 'approaching';
        }

        return [
            'total_volume_m3' => round($totalVolumeM3, 3),
            'total_duration_hours' => round($totalDurationHours, 2),
            'events_count' => $events->count(),
            'area_m2' => round($areaM2, 2),
            'volume_per_m2' => $volumePerM2 !== null ? round($volumePerM2, 4) : null,
            'quota_m3' => $quota !== null ? round($quota, 3) : null,
            'threshold_percent' => $thresholdPercent,
            'alert_threshold_m3' => $alertThresholdM3 !== null ? round($alertThresholdM3, 3) : null,
            'remaining_m3' => $quota !== null ? round(max(0, $quota - $totalVolumeM3), 3) : null,
            'status' => $status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function comparison(Farm $farm): array
    {
        $campaigns = Campaign::query()
            ->forFarm($farm->id)
            ->with(['productionUnit', 'crop'])
            ->with('irrigationEvents')
            ->orderByDesc('start_date')
            ->get();

        $rows = $campaigns->map(function (Campaign $campaign): array {
            $summary = $this->summaryForCampaign($campaign);

            return [
                'campaign' => $campaign,
                'summary' => $summary,
            ];
        });

        return [
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, IrrigationEvent>
     */
    public function eventsForFarm(Farm $farm, ?int $campaignId = null): Collection
    {
        return IrrigationEvent::query()
            ->forFarm($farm->id)
            ->with(['productionUnit', 'campaign.crop', 'recordedBy'])
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
            ->orderByDesc('irrigated_at')
            ->orderByDesc('id')
            ->get();
    }
}
