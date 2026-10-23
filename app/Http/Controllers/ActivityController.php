<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Http\Requests\StoreActivityRequest;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\ProductionUnit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $farmId = $this->farmId();

        $activities = Activity::query()
            ->forFarm($farmId)
            ->with(['campaign.productionUnit', 'assignedTo', 'createdBy'])
            ->when($request->filled('campaign_id'), fn ($query) => $query->where('campaign_id', $request->integer('campaign_id')))
            ->when($request->filled('production_unit_id'), fn ($query) => $query->whereHas(
                'campaign',
                fn ($sub) => $sub->where('production_unit_id', $request->integer('production_unit_id')),
            ))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('created_by'), fn ($query) => $query->where('created_by', $request->integer('created_by')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('planned_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('planned_date', '<=', $request->date('to')))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%'))
            ->when($request->boolean('overdue'), fn ($query) => $query->overdue())
            ->orderBy(
                in_array($request->string('sort')->toString(), ['planned_date', 'status', 'type', 'title'], true)
                    ? $request->string('sort')->toString()
                    : 'planned_date',
                $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
            )
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('activities.index', [
            'activities' => $activities,
            'campaigns' => Campaign::query()->forFarm($farmId)->orderByDesc('start_date')->get(),
            'productionUnits' => $this->productionUnits($farmId),
            'types' => collect(ActivityType::cases()),
            'statuses' => collect(ActivityStatus::cases()),
            'members' => $this->members($farmId),
        ]);
    }

    public function create(Request $request): View
    {
        return view('activities.create', $this->formData() + [
            'activity' => new Activity([
                'campaign_id' => $request->integer('campaign_id') ?: null,
                'type' => ActivityType::SoilPreparation,
                'status' => ActivityStatus::Planned,
                'planned_date' => now()->toDateString(),
                'assigned_to' => $request->user()->id,
            ]),
        ]);
    }

    public function store(StoreActivityRequest $request): RedirectResponse
    {
        Gate::authorize('create', Activity::class);

        $activity = Activity::create($request->toActivityAttributes() + [
            'farm_id' => $this->farmId(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('activities.show', $activity)->with('status', 'Activité enregistrée.');
    }

    public function show(Activity $activity): View
    {
        $activity->load(['campaign.productionUnit', 'calendarStep', 'assignedTo', 'createdBy', 'performedBy']);

        return view('activities.show', compact('activity'));
    }

    public function edit(Activity $activity): View
    {
        return view('activities.edit', $this->formData() + ['activity' => $activity]);
    }

    public function update(StoreActivityRequest $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('update', $activity);

        $activity->update($request->toActivityAttributes());

        return redirect()->route('activities.show', $activity)->with('status', 'Activité mise à jour.');
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        Gate::authorize('delete', $activity);

        if ($activity->performed_at !== null || $activity->status === ActivityStatus::Completed) {
            return back()->withErrors([
                'activity' => 'Une activité réalisée ne peut pas être supprimée ; annulez-la avec un motif.',
            ]);
        }

        $activity->delete();

        return redirect()->route('activities.index')->with('status', 'Activité supprimée.');
    }

    public function complete(Request $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('update', $activity);

        if ($activity->status === ActivityStatus::Completed) {
            return back()->with('status', 'Cette activité est déjà réalisée.');
        }

        $validated = $request->validate([
            'performed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $activity->update([
            'status' => ActivityStatus::Completed,
            'performed_at' => $validated['performed_at'] ?? now(),
            'performed_by' => $request->user()->id,
            'description' => $validated['notes'] ?? $activity->description,
        ]);

        return redirect()->route('activities.show', $activity)->with('status', 'Activité marquée comme réalisée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'campaigns' => Campaign::query()->forFarm($this->farmId())->orderByDesc('start_date')->get(),
            'types' => collect(ActivityType::cases()),
            'statuses' => collect(ActivityStatus::cases()),
            'members' => $this->members($this->farmId()),
        ];
    }

    private function members(int $farmId)
    {
        return User::query()
            ->whereHas('memberships', fn ($query) => $query->where('farm_id', $farmId)->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    private function productionUnits(int $farmId)
    {
        return ProductionUnit::query()->forFarm($farmId)->orderBy('code')->get();
    }
}
