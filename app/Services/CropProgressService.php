<?php

namespace App\Services;

use App\Enums\CropStage;
use App\Models\Campaign;
use App\Models\CampaignStageEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Progression culturale calculée uniquement à partir des étapes réellement
 * enregistrées. Aucune avancée n'est déduite du temps écoulé.
 */
class CropProgressService
{
    /**
     * @return array{
     *     progress: int,
     *     completed: int,
     *     total: int,
     *     current: ?CropStage,
     *     latest_at: ?Carbon,
     *     timeline: list<array{stage: CropStage, recorded: bool, event: ?CampaignStageEvent}>
     * }
     */
    public function forCampaign(Campaign $campaign): array
    {
        /** @var Collection<string, CampaignStageEvent> $events */
        $events = $campaign->stageEvents
            ->sortBy(fn ($event) => $event->stage->position())
            ->keyBy(fn ($event) => $event->stage->value);

        $timeline = [];
        $completed = 0;
        $current = null;
        $latestAt = null;

        foreach (CropStage::ordered() as $stage) {
            $event = $events->get($stage->value);

            if ($event !== null) {
                $completed++;
                $current = $stage;
                $latestAt = $latestAt === null || $event->recorded_at->gt($latestAt)
                    ? $event->recorded_at
                    : $latestAt;
            }

            $timeline[] = [
                'stage' => $stage,
                'recorded' => $event !== null,
                'event' => $event,
            ];
        }

        $total = count(CropStage::ordered());
        $progress = $total > 0 ? (int) round($completed / $total * 100) : 0;

        return [
            'progress' => $progress,
            'completed' => $completed,
            'total' => $total,
            'current' => $current,
            'latest_at' => $latestAt,
            'timeline' => $timeline,
        ];
    }
}
