<?php

namespace App\Http\Controllers;

use App\Enums\IssuePhotoKind;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\Campaign;
use App\Models\CropIssue;
use App\Models\ProductionUnit;
use App\Services\PhotoStorage;
use App\Support\DashboardCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CropIssueController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $farmId = $this->farmId();
        $status = $request->string('status')->toString();

        if ($status === '') {
            $status = 'active';
        }

        $issues = CropIssue::query()
            ->forFarm($farmId)
            ->with(['productionUnit:id,code,name', 'campaign:id,name', 'reportedBy:id,name', 'assignedTo:id,name'])
            ->withCount('photos')
            ->when($status === 'active', fn ($query) => $query->where('status', '<>', IssueStatus::Resolved->value))
            ->when($status !== 'all' && $status !== 'active', fn ($query) => $query->where('status', $status))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('severity'), fn ($query) => $query->where('severity', $request->string('severity')))
            ->when($request->filled('production_unit_id'), fn ($query) => $query->where('production_unit_id', $request->integer('production_unit_id')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn ($query) => $query
                    ->where('title', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $base = CropIssue::query()->forFarm($farmId);

        $summary = [
            'open' => (clone $base)->where('status', '<>', IssueStatus::Resolved->value)->count(),
            'critical' => (clone $base)->where('severity', IssueSeverity::Critical->value)
                ->where('status', '<>', IssueStatus::Resolved->value)->count(),
            'resolved' => (clone $base)->where('status', IssueStatus::Resolved->value)->count(),
            'total' => (clone $base)->count(),
        ];

        return view('issues.index', [
            'issues' => $issues,
            'summary' => $summary,
            'status' => $status,
            'types' => collect(IssueType::cases()),
            'severities' => collect(IssueSeverity::cases()),
            'statuses' => collect(IssueStatus::cases()),
            'units' => ProductionUnit::query()->forFarm($farmId)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', CropIssue::class);

        return view('issues.create', $this->formData() + [
            'issue' => new CropIssue([
                'observed_at' => now()->toDateString(),
                'severity' => IssueSeverity::Moderate,
                'status' => IssueStatus::Open,
                'type' => IssueType::Disease,
                'production_unit_id' => $request->integer('production_unit_id') ?: null,
                'campaign_id' => $request->integer('campaign_id') ?: null,
            ]),
        ]);
    }

    public function store(Request $request, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('create', CropIssue::class);

        $validated = $this->validated($request);
        $validated['farm_id'] = $this->farmId();
        $validated['reported_by'] = $request->user()->id;
        $validated['reference'] = CropIssue::nextReference($this->farmId());

        $issue = DB::transaction(function () use ($validated, $request, $photos): CropIssue {
            $issue = CropIssue::create($validated);

            if ($request->hasFile('photo')) {
                $path = $photos->store($request->file('photo'), 'issue-photos');

                $issue->photos()->create([
                    'farm_id' => $issue->farm_id,
                    'photo_path' => $path,
                    'kind' => IssuePhotoKind::Observation,
                    'caption' => 'Photo à la déclaration',
                    'taken_at' => now(),
                    'uploaded_by' => $request->user()->id,
                ]);
            }

            return $issue;
        });

        DashboardCache::forget($this->farm());

        return redirect()->route('issues.show', $issue)
            ->with('status', 'Problème agricole enregistré sous la référence '.$issue->reference.'.');
    }

    public function show(CropIssue $issue): View
    {
        Gate::authorize('view', $issue);

        $issue->load([
            'productionUnit',
            'campaign.crop',
            'reportedBy',
            'assignedTo',
            'photos.uploadedBy',
            'treatments.input',
            'treatments.appliedBy',
        ]);

        return view('issues.show', [
            'issue' => $issue,
            'beforePhoto' => $issue->photos->firstWhere('kind', IssuePhotoKind::Before),
            'afterPhoto' => $issue->photos->firstWhere('kind', IssuePhotoKind::After),
            'photoKinds' => collect(IssuePhotoKind::cases()),
            'severities' => collect(IssueSeverity::cases()),
            'statuses' => collect(IssueStatus::cases()),
        ]);
    }

    public function edit(CropIssue $issue): View
    {
        Gate::authorize('update', $issue);

        return view('issues.edit', $this->formData() + ['issue' => $issue]);
    }

    public function update(Request $request, CropIssue $issue): RedirectResponse
    {
        Gate::authorize('update', $issue);

        $validated = $this->validated($request, $issue);

        if (($validated['status'] ?? null) === IssueStatus::Resolved->value) {
            $validated['resolved_at'] = $issue->resolved_at ?? now();
        } else {
            $validated['resolved_at'] = null;
        }

        $issue->update($validated);

        DashboardCache::forget($this->farm());

        return redirect()->route('issues.show', $issue)->with('status', 'Problème mis à jour.');
    }

    public function updateStatus(Request $request, CropIssue $issue): RedirectResponse
    {
        Gate::authorize('update', $issue);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(IssueStatus::class)],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated['resolved_at'] = $validated['status'] === IssueStatus::Resolved->value
            ? ($issue->resolved_at ?? now())
            : null;

        $issue->update($validated);

        DashboardCache::forget($this->farm());

        return redirect()->route('issues.show', $issue)->with('status', 'Statut du problème mis à jour.');
    }

    public function destroy(CropIssue $issue, PhotoStorage $photos): RedirectResponse
    {
        Gate::authorize('delete', $issue);

        foreach ($issue->photos as $photo) {
            $photos->delete($photo->photo_path);
        }

        $reference = $issue->reference;
        $issue->delete();

        DashboardCache::forget($this->farm());

        return redirect()->route('issues.index')
            ->with('status', 'Problème '.$reference.' supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $farmId = $this->farmId();

        return [
            'types' => collect(IssueType::cases()),
            'severities' => collect(IssueSeverity::cases()),
            'statuses' => collect(IssueStatus::cases()),
            'units' => ProductionUnit::query()->forFarm($farmId)->orderBy('code')->get(['id', 'code', 'name', 'area_m2']),
            'campaigns' => Campaign::query()->forFarm($farmId)->with(['crop:id,name', 'productionUnit:id,code'])->orderByDesc('start_date')->get(),
            'members' => $this->farm()->users()->wherePivot('is_active', true)->orderBy('name')->get(['users.id', 'users.name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CropIssue $issue = null): array
    {
        $farmId = $this->farmId();

        $validated = $request->validate([
            'production_unit_id' => ['required', Rule::exists('production_units', 'id')->where('farm_id', $farmId)],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('farm_id', $farmId)],
            'type' => ['required', Rule::enum(IssueType::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'observed_at' => ['required', 'date', 'before_or_equal:now'],
            'severity' => ['required', Rule::enum(IssueSeverity::class)],
            'affected_area_m2' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::enum(IssueStatus::class)],
            'recommended_actions' => ['nullable', 'string', 'max:5000'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', Rule::exists('farm_user', 'user_id')->where('farm_id', $farmId)->where('is_active', true)],
            'photo' => ['nullable', 'image', 'max:5120'],
            'is_demo' => ['nullable', 'boolean'],
        ]);

        $unit = ProductionUnit::query()->forFarm($farmId)->findOrFail($validated['production_unit_id']);

        if (isset($validated['affected_area_m2']) && (float) $validated['affected_area_m2'] > (float) $unit->area_m2) {
            throw ValidationException::withMessages([
                'affected_area_m2' => 'La surface affectée ne peut pas dépasser la superficie de la parcelle.',
            ]);
        }

        if (! empty($validated['campaign_id'])) {
            $campaign = Campaign::query()->forFarm($farmId)->findOrFail($validated['campaign_id']);

            if ((int) $campaign->production_unit_id !== (int) $unit->id) {
                throw ValidationException::withMessages([
                    'campaign_id' => 'La campagne sélectionnée n’appartient pas à cette parcelle.',
                ]);
            }
        }

        unset($validated['photo'], $validated['is_demo']);

        return $validated;
    }
}
