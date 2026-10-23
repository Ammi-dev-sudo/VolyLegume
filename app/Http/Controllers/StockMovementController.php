<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\Input;
use App\Models\StockMovement;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $movements = StockMovement::query()
            ->forFarm($this->farmId())
            ->with(['input', 'campaign', 'createdBy'])
            ->when($request->filled('input_id'), fn ($query) => $query->where('input_id', $request->integer('input_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('occurred_at', '<=', $request->date('to')))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('stock-movements.index', [
            'movements' => $movements,
            'inputs' => Input::query()->forFarm($this->farmId())->orderBy('name')->get(),
            'campaigns' => Campaign::query()->forFarm($this->farmId())->orderByDesc('start_date')->get(),
            'types' => collect(StockMovementType::cases()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('stock-movements.create', [
            'inputs' => Input::query()->forFarm($this->farmId())->orderBy('name')->get(),
            'campaigns' => Campaign::query()->forFarm($this->farmId())->orderByDesc('start_date')->get(),
            'types' => collect(StockMovementType::cases()),
            'selectedInput' => $request->integer('input_id') ?: null,
            'selectedType' => $request->string('type')->toString() ?: StockMovementType::Entry->value,
        ]);
    }

    public function store(Request $request, StockService $stock): RedirectResponse
    {
        $farmId = $this->farmId();

        $validated = $request->validate([
            'input_id' => ['required', Rule::exists('inputs', 'id')->where('farm_id', $farmId)],
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('farm_id', $farmId)],
            'reason' => ['required', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        if ($validated['type'] === StockMovementType::Adjustment->value) {
            Gate::authorize('adjust', Input::class);
        }

        $input = Input::query()->forFarm($farmId)->findOrFail($validated['input_id']);
        $campaign = isset($validated['campaign_id'])
            ? Campaign::query()->forFarm($farmId)->findOrFail($validated['campaign_id'])
            : null;

        $type = StockMovementType::from($validated['type']);
        $quantity = (float) $validated['quantity'];

        $delta = match ($type) {
            StockMovementType::Initial, StockMovementType::Entry => abs($quantity),
            StockMovementType::Exit, StockMovementType::Consumption => -abs($quantity),
            StockMovementType::Adjustment => $quantity,
        };

        try {
            $stock->record($input, $type, $delta, $request->user(), $validated['reason'], $campaign, null, isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : null);
        } catch (InsufficientStockException $exception) {
            return back()->withInput()->withErrors(['quantity' => $exception->getMessage()]);
        }

        return redirect()->route('stock-movements.index')->with('status', 'Mouvement de stock enregistré.');
    }
}
