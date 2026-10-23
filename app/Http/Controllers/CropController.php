<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Crop;
use App\Models\CropCategory;
use App\Services\CropGuideService;
use Illuminate\Database\Eloquent\Collection;
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
            ->with('category')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->orderBy('variety')
            ->paginate(15)
            ->withQueryString();

        $categories = $this->categories();

        return view('crops.index', compact('crops', 'categories'));
    }

    public function create(): View
    {
        Gate::authorize('create', Crop::class);

        return view('crops.create', [
            'crop' => new Crop(['production_unit' => 'kg', 'is_active' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request, CropGuideService $guideService): RedirectResponse
    {
        Gate::authorize('create', Crop::class);

        $farmId = $this->farmId();

        $validated = $this->validateCrop($request, $farmId);

        $crop = Crop::create($validated + ['farm_id' => $farmId, 'production_unit' => 'kg']);

        $guideService->sync($crop);

        return redirect()->route('crops.index')
            ->with('status', 'Culture créée.');
    }

    public function guide(Crop $crop, CropGuideService $guideService): View
    {
        $guide = $guideService->ensure($crop);

        $crop->load([
            'category',
            'calendarSteps' => fn ($query) => $query->where('is_active', true)->orderBy('position'),
        ]);

        return view('crops.guide', compact('crop', 'guide'));
    }

    public function edit(Crop $crop): View
    {
        Gate::authorize('update', $crop);

        return view('crops.edit', [
            'crop' => $crop,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Crop $crop, CropGuideService $guideService): RedirectResponse
    {
        Gate::authorize('update', $crop);

        $validated = $this->validateCrop($request, $this->farmId(), $crop->id);

        $crop->update($validated);

        $guideService->sync($crop->refresh());

        return redirect()->route('crops.index')
            ->with('status', 'Culture mise à jour.');
    }

    public function destroy(Crop $crop): RedirectResponse
    {
        Gate::authorize('delete', $crop);

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
            'category_id' => ['required', 'integer', 'exists:crop_categories,id'],
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

    /**
     * @return Collection<int, CropCategory>
     */
    private function categories(): Collection
    {
        return CropCategory::query()
            ->active()
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }
}
