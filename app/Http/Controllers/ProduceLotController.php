<?php

namespace App\Http\Controllers;

use App\Enums\ProduceLotStatus;
use App\Enums\ProduceMovementType;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\ProduceLot;
use App\Services\ProduceStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProduceLotController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $lots = ProduceLot::query()
            ->forFarm($this->farmId())
            ->with(['campaign.crop', 'harvest'])
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->boolean('available'), fn ($query) => $query->available())
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query
                    ->where('code', 'like', '%'.$search.'%')
                    ->orWhere('storage_location', 'like', '%'.$search.'%'));
            })
            ->orderByDesc('harvested_at')
            ->paginate(20)
            ->withQueryString();

        return view('produce-lots.index', [
            'lots' => $lots,
            'campaigns' => Campaign::query()->forFarm($this->farmId())->with('crop')->orderByDesc('start_date')->get(),
            'statuses' => collect(ProduceLotStatus::cases()),
            'availableTotal' => (float) ProduceLot::query()->forFarm($this->farmId())->sum('available_quantity'),
        ]);
    }

    public function show(ProduceLot $produceLot): View
    {
        $produceLot->load([
            'harvest.recordedBy',
            'campaign.crop',
            'campaign.productionUnit',
            'campaign.treatments.input',
            'campaign.activities',
            'movements.createdBy',
            'movements.sale',
            'saleItems.sale.customer',
        ]);

        return view('produce-lots.show', [
            'lot' => $produceLot,
            'movementTypes' => collect([
                ProduceMovementType::Loss,
                ProduceMovementType::Gift,
                ProduceMovementType::Consumption,
                ProduceMovementType::Adjustment,
            ]),
        ]);
    }

    public function storeMovement(Request $request, ProduceLot $produceLot, ProduceStockService $stock): RedirectResponse
    {
        Gate::authorize('adjust', $produceLot);

        $validated = $request->validate([
            'type' => ['required', Rule::in([
                ProduceMovementType::Loss->value,
                ProduceMovementType::Gift->value,
                ProduceMovementType::Consumption->value,
                ProduceMovementType::Adjustment->value,
            ])],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $type = ProduceMovementType::from($validated['type']);
        $quantity = (float) $validated['quantity'];

        if ($type === ProduceMovementType::Adjustment) {
            $stock->adjust($produceLot, $quantity, $request->user(), $validated['reason']);
        } else {
            $stock->remove($produceLot, abs($quantity), $type, $request->user(), $validated['reason']);
        }

        return redirect()->route('produce-lots.show', $produceLot)->with('status', 'Mouvement de stock enregistré.');
    }
}
