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
use Illuminate\Database\UniqueConstraintViolationException;
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
            ->when($request->boolean('critical'), fn ($query) => $query
                ->where('status', ActivityStatus::Planned->value)
                ->whereDate('planned_date', '<', today()->subDays(3)))
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

        $farmId = $this->farmId();
        $attributes = $request->toActivityAttributes();
        $clientReference = $attributes['client_reference'] ?? null;

        if ($clientReference !== null) {
            $existing = Activity::query()
                ->forFarm($farmId)
                ->where('client_reference', $clientReference)
                ->first();

            if ($existing !== null) {
                return redirect()
                    ->route('activities.show', $existing)
                    ->with('status', 'Cette activité a déjà été synchronisée.');
            }
        }

        try {
            $activity = Activity::create($attributes + [
                'farm_id' => $farmId,
                'created_by' => $request->user()->id,
                'overdue_consequence' => $this->consequenceFor($attributes['type'] ?? null),
            ]);
        } catch (UniqueConstraintViolationException) {
            $activity = Activity::query()
                ->forFarm($farmId)
                ->where('client_reference', $clientReference)
                ->firstOrFail();
        }

        return redirect()->route('activities.show', $activity)->with('status', 'Activité enregistrée.');
    }

    public function show(Activity $activity): View
    {
        $activity->load(['campaign.productionUnit', 'calendarStep', 'assignedTo', 'createdBy', 'performedBy']);

        if ($activity->isCriticallyLate() && $activity->overdue_notified_at === null) {
            $activity->forceFill([
                'overdue_notified_at' => now(),
                'overdue_consequence' => $activity->overdue_consequence ?? $this->consequenceFor($activity->type),
            ])->save();
            $activity->refresh();
        } elseif ($activity->isCriticallyLate() && $activity->overdue_consequence === null) {
            $activity->forceFill([
                'overdue_consequence' => $this->consequenceFor($activity->type),
            ])->save();
            $activity->refresh();
        }

        return view('activities.show', compact('activity'));
    }

    public function edit(Activity $activity): View
    {
        return view('activities.edit', $this->formData() + ['activity' => $activity]);
    }

    public function update(StoreActivityRequest $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('update', $activity);

        $attributes = $request->toActivityAttributes();
        if (($attributes['type'] ?? null) !== null && $activity->overdue_consequence === null) {
            $attributes['overdue_consequence'] = $this->consequenceFor($attributes['type']);
        }

        $activity->update($attributes);

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

    private function consequenceFor($type): string
    {
        if ($type instanceof ActivityType) {
            $type = $type->value;
        }

        return match ($type) {
            ActivityType::SoilPreparation->value, ActivityType::Sowing->value, ActivityType::Transplanting->value => 'Retarder cette opération peut réduire l’installation de la culture et resserrer le calendrier global.',
            ActivityType::Irrigation->value => 'Un arrosage en retard peut entraîner un stress hydrique et une baisse de développement.',
            ActivityType::Fertilization->value => 'Un apport fertilisant tardif risque d’être moins efficace et peut déséquilibrer la nutrition.',
            ActivityType::Treatment->value => 'Un traitement en retard peut permettre la progression d’un ravageur/maladie et réduire l’efficacité, ce qui peut impacter le délai avant récolte.',
            ActivityType::Weeding->value => 'Un désherbage tardif favorise la concurrence et complique le travail ultérieur.',
            ActivityType::Harvest->value => 'Un retard de récolte peut dégrader la qualité, augmenter les pertes et limiter les possibilités de commercialisation.',
            ActivityType::Observation->value => 'Sans observation à temps, les problèmes ne sont pas détectés et la réponse est repoussée.',
            ActivityType::Maintenance->value => 'Un entretien reporté augmente le risque de panne et peut bloquer les travaux suivants.',
            default => 'Au-delà de 3 jours de retard, l’exécution de cette étape risque de compresser le calendrier cultural et d’augmenter le risque sur la campagne.',
        };
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
