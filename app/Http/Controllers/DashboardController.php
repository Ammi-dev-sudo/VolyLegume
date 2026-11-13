<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Expense;
use App\Services\DashboardService;
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

        return view('dashboard.index', [
            'metrics' => $dashboard->forFarm($farm, $filters, $canViewFinances),
            'campaigns' => Campaign::query()->forFarm($farm->id)->orderByDesc('start_date')->get(),
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
