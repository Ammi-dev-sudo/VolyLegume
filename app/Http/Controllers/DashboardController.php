<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Services\DashboardService;
use Illuminate\Http\Request;
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

        $filters = [
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'campaign_id' => $request->integer('campaign_id') ?: null,
        ];

        return view('dashboard.index', [
            'metrics' => $dashboard->forFarm($farm, $filters),
            'campaigns' => Campaign::query()->forFarm($farm->id)->orderByDesc('start_date')->get(),
            'filters' => $filters,
        ]);
    }
}
