<?php

namespace App\Http\Controllers;

use App\Enums\BaseUnit;
use App\Enums\InputCategory;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Input;
use App\Services\StockService;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InputController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $inputs = Input::query()
            ->forFarm($this->farmId())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->boolean('critical'), fn ($query) => $query->whereColumn('quantity', '<', 'minimum_stock'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('inputs.index', [
            'inputs' => $inputs,
            'categories' => collect(InputCategory::cases()),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Input::class);

        return view('inputs.create', [
            'input' => new Input(['category' => InputCategory::Consumable, 'base_unit' => BaseUnit::Kg, 'minimum_stock' => 0]),
            'categories' => collect(InputCategory::cases()),
            'units' => collect(BaseUnit::cases()),
        ]);
    }

    public function store(Request $request, StockService $stock): RedirectResponse
    {
        Gate::authorize('create', Input::class);

        $validated = $this->validated($request);

        $input = Input::create($validated + [
            'farm_id' => $this->farmId(),
            'quantity' => 0,
        ]);

        $initial = (float) $request->input('initial_quantity', 0);

        if ($initial > 0) {
            $stock->setInitial($input, $initial, $request->user(), 'Stock initial à la création');
        }

        return redirect()->route('inputs.show', $input)->with('status', 'Intrant créé.');
    }

    public function show(Input $input): View
    {
        return view('inputs.show', [
            'input' => $input,
            'movements' => $input->stockMovements()->with(['campaign', 'createdBy'])->orderByDesc('occurred_at')->paginate(15, ['*'], 'movements_page'),
            'treatments' => $input->treatments()->with('campaign')->orderByDesc('applied_at')->limit(10)->get(),
        ]);
    }

    public function edit(Input $input): View
    {
        Gate::authorize('update', $input);

        return view('inputs.edit', [
            'input' => $input,
            'categories' => collect(InputCategory::cases()),
            'units' => collect(BaseUnit::cases()),
        ]);
    }

    public function update(Request $request, Input $input): RedirectResponse
    {
        Gate::authorize('update', $input);

        $input->update($this->validated($request, $input));

        DashboardCache::forget($this->farm());

        return redirect()->route('inputs.show', $input)->with('status', 'Intrant mis à jour.');
    }

    public function destroy(Input $input): RedirectResponse
    {
        Gate::authorize('delete', $input);

        if ($input->stockMovements()->exists()) {
            return back()->withErrors([
                'input' => 'Cet intrant possède un historique de stock ; désactivez-le plutôt que de le supprimer.',
            ]);
        }

        $input->delete();

        return redirect()->route('inputs.index')->with('status', 'Intrant supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Input $input = null): array
    {
        $farmId = $this->farmId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('inputs', 'code')
                ->where('farm_id', $farmId)
                ->when($input !== null, fn ($rule) => $rule->ignore($input->id))],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::enum(InputCategory::class)],
            'base_unit' => ['required', Rule::enum(BaseUnit::class)],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'default_pre_harvest_days' => ['nullable', 'integer', 'min:0'],
            'delay_source' => ['nullable', 'string', 'max:255'],
            'initial_quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $category = InputCategory::from($validated['category']);

        if ($category->requiresPreHarvestDelay()
            && ($validated['default_pre_harvest_days'] === null || blank($validated['delay_source'] ?? null))) {
            throw ValidationException::withMessages([
                'default_pre_harvest_days' => 'Un produit phytosanitaire utilisable exige un délai et sa source.',
            ]);
        }

        $validated['is_active'] = $request->boolean('is_active');

        unset($validated['initial_quantity']);

        return $validated;
    }
}
