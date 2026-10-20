<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Input;
use App\Models\PhytosanitaryTreatment;
use App\Services\TreatmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TreatmentController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $labels = $this->options();

        $treatments = PhytosanitaryTreatment::query()
            ->forFarm($this->farmId())
            ->with(['campaign.crop', 'input', 'appliedBy'])
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('input_id'), fn ($query) => $query->where('input_id', $request->integer('input_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('applied_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('applied_at', '<=', $request->date('to')))
            ->orderByDesc('applied_at')
            ->paginate(15)
            ->withQueryString();

        return view('treatments.index', $labels + ['treatments' => $treatments]);
    }

    public function create(Request $request): View
    {
        return view('treatments.create', $this->options() + [
            'phiInputs' => $this->phiInputs(),
            'selectedCampaign' => $request->integer('campaign_id') ?: null,
        ]);
    }

    public function store(Request $request, TreatmentService $service): RedirectResponse
    {
        $farmId = $this->farmId();

        $validated = $request->validate([
            'campaign_id' => ['required', Rule::exists('campaigns', 'id')->where('farm_id', $farmId)],
            'input_id' => ['required', Rule::exists('inputs', 'id')->where('farm_id', $farmId)->where('category', 'phytosanitary')],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'dose_value' => ['required', 'numeric', 'gt:0'],
            'dose_unit' => ['required', 'string', 'max:20'],
            'pre_harvest_days' => ['required', 'integer', 'min:0'],
            'applied_at' => ['required', 'date', 'before_or_equal:now'],
            'delay_source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $campaign = Campaign::query()->forFarm($farmId)->findOrFail($validated['campaign_id']);
        $input = Input::query()->forFarm($farmId)->findOrFail($validated['input_id']);

        try {
            $treatment = $service->record($campaign, $input, $request->user(), $validated);
        } catch (\InvalidArgumentException|InsufficientStockException $exception) {
            return back()->withInput()->withErrors(['quantity' => $exception->getMessage()]);
        }

        return redirect()->route('campaigns.show', $campaign)
            ->with('status', 'Traitement enregistré — récolte possible à partir du '.$treatment->minimum_harvest_at->format('d/m/Y H:i').'.');
    }

    public function show(PhytosanitaryTreatment $treatment): View
    {
        $treatment->load(['campaign.crop', 'input', 'activity', 'stockMovement', 'appliedBy']);

        return view('treatments.show', compact('treatment'));
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'campaigns' => Campaign::query()->forFarm($this->farmId())->orderByDesc('start_date')->get(),
            'inputs' => Input::query()->forFarm($this->farmId())->orderBy('name')->get(),
        ];
    }

    private function phiInputs()
    {
        return Input::query()
            ->forFarm($this->farmId())
            ->where('category', 'phytosanitary')
            ->whereNotNull('default_pre_harvest_days')
            ->whereNotNull('delay_source')
            ->orderBy('name')
            ->get();
    }
}
