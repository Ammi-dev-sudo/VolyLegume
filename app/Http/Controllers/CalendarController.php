<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Harvest;
use App\Models\PhytosanitaryTreatment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Agenda mensuel de l'exploitation : activités, récoltes, traitements et
 * campagnes sont regroupés sur une grille pour repérer rapidement chaque
 * échéance.
 */
class CalendarController extends Controller
{
    use UsesFarmContext;

    /** @var array<int, string> */
    private const MONTHS = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    public function index(Request $request): View
    {
        $farmId = $this->farmId();
        $campaignId = $request->integer('campaign_id') ?: null;

        $view = $request->string('view')->toString();

        if (! in_array($view, ['month', 'week', 'day'], true)) {
            $view = 'month';
        }

        $anchor = $this->resolveDate($request->string('date')->toString());

        if ($view === 'month') {
            $monthStart = $this->resolveMonth($request->string('month')->toString());
            $rangeStart = $monthStart->copy();
            $rangeEnd = $monthStart->copy()->endOfMonth();
            $gridStart = $rangeStart->copy()->startOfWeek(Carbon::MONDAY);
            $gridEnd = $rangeEnd->copy()->endOfWeek(Carbon::SUNDAY);
        } elseif ($view === 'week') {
            $rangeStart = $anchor->copy()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $anchor->copy()->endOfWeek(Carbon::SUNDAY);
            $monthStart = $anchor->copy()->startOfMonth();
            $gridStart = $rangeStart->copy();
            $gridEnd = $rangeEnd->copy();
        } else {
            $rangeStart = $anchor->copy()->startOfDay();
            $rangeEnd = $anchor->copy()->endOfDay();
            $monthStart = $anchor->copy()->startOfMonth();
            $gridStart = $rangeStart->copy();
            $gridEnd = $rangeEnd->copy();
        }

        $show = $request->string('show')->toString();

        if (! in_array($show, ['all', 'activities', 'harvests', 'treatments', 'campaigns'], true)) {
            $show = 'all';
        }

        $events = [];

        if (in_array($show, ['all', 'activities'], true)) {
            $activities = Activity::query()
                ->forFarm($farmId)
                ->whereDate('planned_date', '>=', $gridStart->toDateString())
                ->whereDate('planned_date', '<=', $gridEnd->toDateString())
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->with('campaign:id,name')
                ->orderBy('planned_date')
                ->orderBy('id')
                ->get();

            foreach ($activities as $activity) {
                $color = match (true) {
                    $activity->status === ActivityStatus::Cancelled => 'slate',
                    $activity->status === ActivityStatus::Completed => 'emerald',
                    $activity->isLate() => 'red',
                    default => 'sky',
                };

                $this->pushEvent($events, $activity->planned_date, [
                    'kind' => 'activity',
                    'label' => $activity->title,
                    'meta' => $activity->type->label().($activity->campaign ? ' · '.$activity->campaign->name : ''),
                    'color' => $color,
                    'url' => route('activities.show', $activity),
                    'time' => null,
                    'sort' => '00:00',
                ]);
            }
        }

        if (in_array($show, ['all', 'harvests'], true)) {
            $harvests = Harvest::query()
                ->forFarm($farmId)
                ->whereBetween('harvested_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->with('campaign:id,name')
                ->orderBy('harvested_at')
                ->get();

            foreach ($harvests as $harvest) {
                $this->pushEvent($events, $harvest->harvested_at, [
                    'kind' => 'harvest',
                    'label' => 'Récolte · '.number_format((float) $harvest->quantity, 1, ',', ' ').' kg',
                    'meta' => $harvest->campaign?->name ?? 'Récolte',
                    'color' => 'amber',
                    'url' => route('harvests.show', $harvest),
                    'time' => $harvest->harvested_at->format('H:i'),
                    'sort' => $harvest->harvested_at->format('H:i'),
                ]);
            }
        }

        if (in_array($show, ['all', 'treatments'], true)) {
            $treatments = PhytosanitaryTreatment::query()
                ->forFarm($farmId)
                ->where(function ($query) use ($gridStart, $gridEnd): void {
                    $query->whereBetween('applied_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
                        ->orWhereBetween('minimum_harvest_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()]);
                })
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->with(['campaign:id,name', 'input:id,name'])
                ->orderBy('applied_at')
                ->get();

            foreach ($treatments as $treatment) {
                $inputName = $treatment->input?->name ?? 'Traitement';

                if ($treatment->applied_at->between($gridStart, $gridEnd->copy()->endOfDay())) {
                    $this->pushEvent($events, $treatment->applied_at, [
                        'kind' => 'treatment',
                        'label' => 'Traitement · '.$inputName,
                        'meta' => $treatment->campaign?->name ?? 'Traitement phyto',
                        'color' => 'rose',
                        'url' => route('treatments.show', $treatment),
                        'time' => $treatment->applied_at->format('H:i'),
                        'sort' => $treatment->applied_at->format('H:i'),
                    ]);
                }

                if ($treatment->minimum_harvest_at->between($gridStart, $gridEnd->copy()->endOfDay())) {
                    $this->pushEvent($events, $treatment->minimum_harvest_at, [
                        'kind' => 'harvest-delay',
                        'label' => 'Récolte possible · '.$inputName,
                        'meta' => $treatment->campaign?->name ?? 'Fin du délai avant récolte',
                        'color' => 'lime',
                        'url' => route('treatments.show', $treatment),
                        'time' => $treatment->minimum_harvest_at->format('H:i'),
                        'sort' => $treatment->minimum_harvest_at->format('H:i'),
                    ]);
                }
            }
        }

        if (in_array($show, ['all', 'campaigns'], true)) {
            $campaigns = Campaign::query()
                ->forFarm($farmId)
                ->when($campaignId, fn ($query) => $query->whereKey($campaignId))
                ->where(function ($query) use ($gridStart, $gridEnd): void {
                    $query->whereDate('start_date', '>=', $gridStart->toDateString())
                        ->whereDate('start_date', '<=', $gridEnd->toDateString())
                        ->orWhere(function ($query) use ($gridStart, $gridEnd): void {
                            $query->whereDate('expected_end_date', '>=', $gridStart->toDateString())
                                ->whereDate('expected_end_date', '<=', $gridEnd->toDateString());
                        })
                        ->orWhere(function ($query) use ($gridStart, $gridEnd): void {
                            $query->whereDate('actual_end_date', '>=', $gridStart->toDateString())
                                ->whereDate('actual_end_date', '<=', $gridEnd->toDateString());
                        });
                })
                ->orderBy('start_date')
                ->get();

            foreach ($campaigns as $campaign) {
                $this->pushEvent($events, $campaign->start_date, [
                    'kind' => 'campaign-start',
                    'label' => 'Début · '.$campaign->name,
                    'meta' => 'Nouvelle campagne',
                    'color' => 'violet',
                    'url' => route('campaigns.show', $campaign),
                    'time' => null,
                    'sort' => '00:00',
                ]);

                $this->pushEvent($events, $campaign->expected_end_date, [
                    'kind' => 'campaign-end',
                    'label' => 'Fin prévue · '.$campaign->name,
                    'meta' => 'Fin de campagne',
                    'color' => 'indigo',
                    'url' => route('campaigns.show', $campaign),
                    'time' => null,
                    'sort' => '23:59',
                ]);

                if ($campaign->actual_end_date !== null) {
                    $this->pushEvent($events, $campaign->actual_end_date, [
                        'kind' => 'campaign-closed',
                        'label' => 'Clôturée · '.$campaign->name,
                        'meta' => 'Campagne terminée',
                        'color' => 'purple',
                        'url' => route('campaigns.show', $campaign),
                        'time' => null,
                        'sort' => '23:59',
                    ]);
                }
            }
        }

        foreach ($events as $date => $dayEvents) {
            usort($dayEvents, fn (array $first, array $second): int => strcmp($first['sort'], $second['sort']));

            $events[$date] = $dayEvents;
        }

        $days = [];

        for ($cursor = $gridStart->copy(); $cursor->lte($gridEnd); $cursor->addDay()) {
            $days[] = $cursor->copy();
        }

        $rangeLabel = match ($view) {
            'week' => 'Semaine du '.$rangeStart->format('d/m/Y').' au '.$rangeEnd->format('d/m/Y'),
            'day' => $rangeStart->translatedFormat('l j F Y'),
            default => self::MONTHS[$monthStart->month].' '.$monthStart->year,
        };

        $previousAnchor = match ($view) {
            'week' => $anchor->copy()->subWeek(),
            'day' => $anchor->copy()->subDay(),
            default => $rangeStart->copy()->subMonthNoOverflow(),
        };

        $nextAnchor = match ($view) {
            'week' => $anchor->copy()->addWeek(),
            'day' => $anchor->copy()->addDay(),
            default => $rangeStart->copy()->addMonthNoOverflow(),
        };

        return view('calendar.index', [
            'days' => $days,
            'events' => $events,
            'view' => $view,
            'anchor' => $anchor,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'rangeLabel' => $rangeLabel,
            'monthStart' => $monthStart,
            'previousLink' => $this->calendarUrl($view, $previousAnchor, $show, $campaignId),
            'nextLink' => $this->calendarUrl($view, $nextAnchor, $show, $campaignId),
            'todayLink' => $this->calendarUrl($view, now(), $show, $campaignId),
            'viewLinks' => [
                'month' => $this->calendarUrl('month', $anchor, $show, $campaignId),
                'week' => $this->calendarUrl('week', $anchor, $show, $campaignId),
                'day' => $this->calendarUrl('day', $anchor, $show, $campaignId),
            ],
            'show' => $show,
            'campaignId' => $campaignId,
            'campaigns' => Campaign::query()->forFarm($farmId)->orderByDesc('start_date')->get(['id', 'name']),
            'summary' => $this->summary($farmId, $rangeStart, $rangeEnd, $campaignId),
        ]);
    }

    private function resolveMonth(string $month): Carbon
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function resolveDate(string $date): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        }

        return now()->startOfDay();
    }

    private function calendarUrl(string $view, Carbon $anchor, string $show, ?int $campaignId): string
    {
        $parameters = [
            'view' => $view,
            'campaign_id' => $campaignId,
        ];

        if ($view === 'month') {
            $parameters['month'] = $anchor->format('Y-m');
        } else {
            $parameters['date'] = $anchor->toDateString();
        }

        if ($show !== 'all') {
            $parameters['show'] = $show;
        }

        return route('calendar.index', array_filter($parameters, fn ($value): bool => $value !== null));
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $events
     * @param  array<string, mixed>  $event
     */
    private function pushEvent(array &$events, Carbon|string $date, array $event): void
    {
        $key = $date instanceof Carbon ? $date->toDateString() : $date;

        $events[$key][] = $event;
    }

    /**
     * @return array{activities: int, late: int, harvests: int, treatments: int, campaigns: int}
     */
    private function summary(int $farmId, Carbon $monthStart, Carbon $monthEnd, ?int $campaignId): array
    {
        $activities = Activity::query()
            ->forFarm($farmId)
            ->whereDate('planned_date', '>=', $monthStart->toDateString())
            ->whereDate('planned_date', '<=', $monthEnd->toDateString())
            ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId));

        return [
            'activities' => (clone $activities)->count(),
            'late' => (clone $activities)->overdue()->count(),
            'harvests' => Harvest::query()
                ->forFarm($farmId)
                ->whereBetween('harvested_at', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()])
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->count(),
            'treatments' => PhytosanitaryTreatment::query()
                ->forFarm($farmId)
                ->whereBetween('applied_at', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()])
                ->when($campaignId, fn ($query) => $query->where('campaign_id', $campaignId))
                ->count(),
            'campaigns' => Campaign::query()
                ->forFarm($farmId)
                ->when($campaignId, fn ($query) => $query->whereKey($campaignId))
                ->whereDate('start_date', '<=', $monthEnd->toDateString())
                ->whereDate('expected_end_date', '>=', $monthStart->toDateString())
                ->count(),
        ];
    }
}
