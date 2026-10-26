<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\ProductionUnit;
use App\Services\FarmMapService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FarmMapController extends Controller
{
    use UsesFarmContext;

    public function index(FarmMapService $map): View
    {
        Gate::authorize('viewAny', ProductionUnit::class);

        return view('farm-map.index', [
            'farm' => $this->farm(),
            'map' => $map->forFarm($this->farm()),
        ]);
    }
}
