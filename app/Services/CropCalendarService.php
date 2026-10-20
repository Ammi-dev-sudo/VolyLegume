<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\CalendarStep;
use App\Models\Campaign;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Génération paramétrable du calendrier cultural (module personnel).
 *
 * Les dates prévues sont calculées depuis `campaigns.start_date` (date de
 * référence explicite), jamais depuis la date de création de la parcelle.
 * La génération est idempotente (RG15) et préserve les activités réalisées.
 */
class CropCalendarService
{
    /**
     * @return Collection<int, array{step: CalendarStep, planned_date: CarbonInterface}>
     */
    public function preview(Campaign $campaign): Collection
    {
        $campaign->loadMissing('crop');

        return $campaign->crop->calendarSteps()
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(fn ($step) => [
                'step' => $step,
                'planned_date' => $campaign->start_date->copy()->addDays($step->offset_days),
            ]);
    }

    /**
     * @param  list<int>|null  $stepIds
     * @return array{created: int, updated: int, preserved: int}
     */
    public function generate(Campaign $campaign, User $actor, ?array $stepIds = null): array
    {
        return DB::transaction(function () use ($campaign, $actor, $stepIds) {
            $created = 0;
            $updated = 0;
            $preserved = 0;

            foreach ($this->preview($campaign) as $row) {
                $step = $row['step'];

                if ($stepIds !== null && ! in_array($step->id, $stepIds, true)) {
                    continue;
                }

                $existing = $campaign->activities()
                    ->where('calendar_step_id', $step->id)
                    ->first();

                if ($existing !== null) {
                    if ($existing->status === ActivityStatus::Planned) {
                        $existing->update([
                            'type' => $step->activity_type,
                            'title' => $step->name,
                            'planned_date' => $row['planned_date'],
                        ]);
                        $updated++;
                    } else {
                        $preserved++;
                    }

                    continue;
                }

                $campaign->activities()->create([
                    'farm_id' => $campaign->farm_id,
                    'calendar_step_id' => $step->id,
                    'type' => $step->activity_type,
                    'title' => $step->name,
                    'description' => $step->description,
                    'planned_date' => $row['planned_date'],
                    'status' => ActivityStatus::Planned,
                    'created_by' => $actor->id,
                ]);

                $created++;
            }

            return ['created' => $created, 'updated' => $updated, 'preserved' => $preserved];
        });
    }

    /**
     * Replanifie les activités non réalisées après un changement de date de
     * référence, sans toucher aux opérations réalisées ou annulées.
     */
    public function replan(Campaign $campaign): int
    {
        return DB::transaction(function () use ($campaign) {
            $count = 0;

            foreach ($this->preview($campaign) as $row) {
                $updated = Activity::query()
                    ->where('campaign_id', $campaign->id)
                    ->where('calendar_step_id', $row['step']->id)
                    ->where('status', ActivityStatus::Planned->value)
                    ->update(['planned_date' => $row['planned_date']]);

                $count += $updated;
            }

            return $count;
        });
    }
}
