<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\CalendarStep;
use App\Models\Crop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CalendarStepController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $crops = Crop::query()->forFarm($this->farmId())->orderBy('name')->get();

        $steps = CalendarStep::query()
            ->forFarm($this->farmId())
            ->with('crop')
            ->when($request->filled('crop_id'), fn ($query) => $query->where('crop_id', $request->integer('crop_id')))
            ->orderBy('crop_id')
            ->orderBy('position')
            ->paginate(20)
            ->withQueryString();

        return view('calendar-steps.index', compact('steps', 'crops'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', CalendarStep::class);

        return view('calendar-steps.create', [
            'step' => new CalendarStep([
                'crop_id' => $request->integer('crop_id') ?: null,
                'activity_type' => ActivityType::Observation,
                'offset_days' => 0,
                'is_active' => true,
            ]),
            'crops' => Crop::query()->forFarm($this->farmId())->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', CalendarStep::class);

        CalendarStep::create($this->validated($request) + ['farm_id' => $this->farmId()]);

        return redirect()->route('calendar-steps.index')->with('status', 'Étape de calendrier créée.');
    }

    public function edit(CalendarStep $calendarStep): View
    {
        Gate::authorize('update', $calendarStep);

        return view('calendar-steps.edit', [
            'step' => $calendarStep,
            'crops' => Crop::query()->forFarm($this->farmId())->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CalendarStep $calendarStep): RedirectResponse
    {
        Gate::authorize('update', $calendarStep);

        $calendarStep->update($this->validated($request, $calendarStep));

        return redirect()->route('calendar-steps.index')->with('status', 'Étape mise à jour.');
    }

    public function destroy(CalendarStep $calendarStep): RedirectResponse
    {
        Gate::authorize('delete', $calendarStep);

        if ($calendarStep->activities()->exists()) {
            $calendarStep->update(['is_active' => false]);

            return redirect()->route('calendar-steps.index')
                ->with('status', 'Étape référencée par des activités : elle a été désactivée.');
        }

        $calendarStep->delete();

        return redirect()->route('calendar-steps.index')->with('status', 'Étape supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CalendarStep $step = null): array
    {
        $farmId = $this->farmId();

        $validated = $request->validate([
            'crop_id' => ['required', Rule::exists('crops', 'id')->where('farm_id', $farmId)],
            'name' => ['required', 'string', 'max:120'],
            'activity_type' => ['required', Rule::enum(ActivityType::class)],
            'offset_days' => ['required', 'integer'],
            'position' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $duplicate = CalendarStep::query()
            ->where('crop_id', $validated['crop_id'])
            ->where('position', $validated['position'])
            ->when($step !== null, fn ($query) => $query->whereKeyNot($step->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'position' => 'Cette position est déjà utilisée pour cette culture.',
            ]);
        }

        return $validated;
    }
}
