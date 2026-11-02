<?php

namespace App\Http\Controllers;

use App\Exceptions\HarvestTooEarlyException;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Http\Requests\StoreHarvestRequest;
use App\Models\Campaign;
use App\Models\Harvest;
use App\Services\HarvestService;
use App\Services\TreatmentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HarvestController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $harvests = Harvest::query()
            ->forFarm($this->farmId())
            ->with(['campaign.crop', 'recordedBy'])
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('harvested_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('harvested_at', '<=', $request->date('to')))
            ->orderByDesc('harvested_at')
            ->paginate(15)
            ->withQueryString();

        return view('harvests.index', [
            'harvests' => $harvests,
            'campaigns' => Campaign::query()->forFarm($this->farmId())->orderByDesc('start_date')->get(),
        ]);
    }

    public function create(Request $request, TreatmentService $treatments): View
    {
        $selectedCampaign = $request->integer('campaign_id') ?: null;
        $campaign = $selectedCampaign !== null
            ? Campaign::query()->forFarm($this->farmId())->with('crop')->find($selectedCampaign)
            : null;

        return view('harvests.create', [
            'campaigns' => Campaign::query()->forFarm($this->farmId())->with('crop')->orderByDesc('start_date')->get(),
            'selectedCampaign' => $selectedCampaign,
            'minimumHarvestAt' => $campaign !== null ? $treatments->minimumHarvestAt($campaign, now()) : null,
        ]);
    }

    public function store(StoreHarvestRequest $request, HarvestService $service): RedirectResponse
    {
        Gate::authorize('create', Harvest::class);

        $farmId = $this->farmId();
        $validated = $request->toHarvestData();
        $clientReference = $validated['client_reference'] ?? null;

        if ($clientReference !== null) {
            $existing = Harvest::query()
                ->forFarm($farmId)
                ->where('client_reference', $clientReference)
                ->first();

            if ($existing !== null) {
                return redirect()
                    ->route('campaigns.show', $existing->campaign_id)
                    ->with('status', 'Cette récolte a déjà été synchronisée.');
            }
        }

        $campaign = Campaign::query()->forFarm($farmId)->with('crop')->findOrFail($validated['campaign_id']);

        try {
            $harvest = $service->record($campaign, $request->user(), $validated);
        } catch (HarvestTooEarlyException $exception) {
            return back()->withInput()->withErrors(['harvested_at' => $exception->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            $harvest = Harvest::query()
                ->forFarm($farmId)
                ->where('client_reference', $clientReference)
                ->firstOrFail();
        }

        return redirect()->route('campaigns.show', $harvest->campaign_id)->with('status', 'Récolte enregistrée.');
    }

    public function show(Harvest $harvest): View
    {
        $harvest->load(['campaign.crop', 'recordedBy', 'revenues', 'produceLot']);

        return view('harvests.show', compact('harvest'));
    }

    public function destroy(Harvest $harvest): RedirectResponse
    {
        Gate::authorize('delete', $harvest);

        if ($harvest->revenues()->exists()) {
            return back()->withErrors([
                'harvest' => 'Cette récolte est liée à des recettes ; supprimez d’abord ces recettes.',
            ]);
        }

        $harvest->load('produceLot');
        $lot = $harvest->produceLot;

        if ($lot !== null) {
            $hasCommercialHistory = $lot->saleItems()->exists()
                || $lot->movements()->where('type', '<>', 'harvest')->exists();

            if ($hasCommercialHistory) {
                return back()->withErrors([
                    'harvest' => 'Le lot de stock possède des mouvements ou des ventes : il ne peut pas être supprimé.',
                ]);
            }

            $lot->movements()->delete();
            $lot->delete();
        }

        $campaign = $harvest->campaign;
        $harvest->delete();

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Récolte et lot de stock supprimés.');
    }
}
