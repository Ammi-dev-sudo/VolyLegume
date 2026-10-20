<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Crop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CropController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $crops = Crop::query()
            ->forFarm($this->farmId())
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->orderBy('variety')
            ->paginate(15)
            ->withQueryString();

        return view('crops.index', compact('crops'));
    }

    public function create(): View
    {
        Gate::authorize('manage-referentials');

        return view('crops.create', ['crop' => new Crop(['production_unit' => 'kg', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-referentials');

        $farmId = $this->farmId();

        $validated = $this->validateCrop($request, $farmId);

        Crop::create($validated + ['farm_id' => $farmId, 'production_unit' => 'kg']);

        return redirect()->route('crops.index')
            ->with('status', 'Culture créée.');
    }

    public function edit(Crop $crop): View
    {
        Gate::authorize('manage-referentials');

        return view('crops.edit', compact('crop'));
    }

    public function update(Request $request, Crop $crop): RedirectResponse
    {
        Gate::authorize('manage-referentials');

        $validated = $this->validateCrop($request, $this->farmId(), $crop->id);

        $crop->update($validated);

        return redirect()->route('crops.index')
            ->with('status', 'Culture mise à jour.');
    }

    public function destroy(Crop $crop): RedirectResponse
    {
        Gate::authorize('manage-referentials');

        if ($crop->campaigns()->exists() || $crop->calendarSteps()->exists()) {
            return back()->withErrors([
                'crop' => 'Cette culture est référencée par des campagnes ou des étapes de calendrier ; désactivez-la plutôt.',
            ]);
        }

        $crop->delete();

        return redirect()->route('crops.index')
            ->with('status', 'Culture supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCrop(Request $request, int $farmId, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'variety' => ['nullable', 'string', 'max:100'],
            'estimated_cycle_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $variety = $request->input('variety') ?: 'non_precisee';

        $duplicate = Crop::query()->forFarm($farmId)
            ->where('name', $validated['name'])
            ->where('variety', $variety)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'Cette culture avec cette variété existe déjà pour l’exploitation.',
            ]);
        }

        return $validated + [
            'variety' => $variety,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
