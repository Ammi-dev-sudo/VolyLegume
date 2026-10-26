<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\IrrigationEvent;
use App\Models\ProductionUnit;
use App\Services\IrrigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IrrigationController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request, IrrigationService $service): View
    {
        Gate::authorize('viewAny', IrrigationEvent::class);

        $campaignId = $request->integer('campaign_id') ?: null;

        return view('irrigation.index', [
            'events' => $service->eventsForFarm($this->farm(), $campaignId),
            'campaigns' => Campaign::query()->forFarm($this->farmId())->orderByDesc('start_date')->get(),
            'selectedCampaignId' => $campaignId,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', IrrigationEvent::class);

        return view('irrigation.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', IrrigationEvent::class);

        $farmId = $this->farmId();

        $validated = $request->validate([
            'campaign_id' => ['required', 'integer', Rule::exists('campaigns', 'id')->where('farm_id', $farmId)],
            'production_unit_id' => ['required', 'integer', Rule::exists('production_units', 'id')->where('farm_id', $farmId)],
            'irrigated_at' => ['required', 'date'],
            'volume_m3' => ['required', 'numeric', 'gt:0'],
            'duration_hours' => ['nullable', 'numeric', 'gt:0'],
            'method' => ['nullable', 'string', 'max:60'],
            'source' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
        ]);

        $campaign = Campaign::query()->forFarm($farmId)->findOrFail($validated['campaign_id']);
        $unit = ProductionUnit::query()->forFarm($farmId)->findOrFail($validated['production_unit_id']);

        if ($campaign->production_unit_id !== $unit->id) {
            return back()->withErrors(['production_unit_id' => 'La parcelle doit correspondre à la campagne.'])->withInput();
        }

        IrrigationEvent::create($validated + [
            'farm_id' => $farmId,
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('irrigation.index')->with('status', 'Arrosage enregistré.');
    }

    public function comparison(IrrigationService $service): View
    {
        Gate::authorize('viewAny', IrrigationEvent::class);

        return view('irrigation.comparison', $service->comparison($this->farm()));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'campaigns' => Campaign::query()->forFarm($this->farmId())->orderByDesc('start_date')->get(),
            'productionUnits' => ProductionUnit::query()->forFarm($this->farmId())->orderBy('code')->get(),
        ];
    }
}
