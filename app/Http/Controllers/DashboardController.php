<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Prediction;
use App\Services\DashboardService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request, DashboardService $dashboard): View
    {
        $farm = $this->context()->farm();

        if ($farm === null) {
            return view('dashboard.empty');
        }

        $preset = $request->string('period')->toString();
        $preset = in_array($preset, ['today', 'week', 'month', 'year', 'custom'], true)
            ? $preset
            : ($request->filled('from') || $request->filled('to') ? 'custom' : 'year');

        [$from, $to] = $this->resolvePeriod($request, $preset);

        $filters = [
            'period' => $preset,
            'from' => $from,
            'to' => $to,
            'campaign_id' => $request->integer('campaign_id') ?: null,
        ];

        $canViewFinances = Gate::allows('viewAny', Expense::class);

        $yieldPredictions = Prediction::query()
            ->forFarm($farm->id)
            ->forModel('yield')
            ->where('subject_type', Campaign::class)
            ->whereHasMorph('subject', [Campaign::class], fn ($query) => $query->active())
            ->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([Campaign::class => ['crop']])])
            ->orderByDesc('value')
            ->limit(6)
            ->get();

        $activityRiskPredictions = Prediction::query()
            ->forFarm($farm->id)
            ->forModel('activity_risk')
            ->where('subject_type', Activity::class)
            ->whereHasMorph('subject', [Activity::class], fn ($query) => $query->where('status', ActivityStatus::Planned->value))
            ->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([Activity::class => ['campaign']])])
            ->orderByDesc('value')
            ->limit(5)
            ->get();

        $customerSegments = $canViewFinances
            ? Prediction::query()
                ->forFarm($farm->id)
                ->forModel('customer_segment')
                ->where('subject_type', Customer::class)
                ->with('subject')
                ->orderByDesc('value')
                ->get()
            : collect();

        return view('dashboard.index', [
            'metrics' => $dashboard->forFarm($farm, $filters, $canViewFinances),
            'campaigns' => Campaign::query()->forFarm($farm->id)->orderByDesc('start_date')->get(),
            'yieldPredictions' => $yieldPredictions,
            'activityRiskPredictions' => $activityRiskPredictions,
            'customerSegments' => $customerSegments,
            'filters' => $filters,
            'canViewFinances' => $canViewFinances,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePeriod(Request $request, string $preset): array
    {
        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();

        if ($preset === 'custom' || $from !== null || $to !== null) {
            $from ??= now()->startOfMonth()->toDateString();
            $to ??= now()->toDateString();

            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            return [$from, $to];
        }

        return match ($preset) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            default => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
        };
    }
}
