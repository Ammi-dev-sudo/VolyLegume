<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FarmStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Farm;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FarmController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
        Gate::authorize('manage', User::class);
    }

    public function index(Request $request): View
    {
        $farms = Farm::query()
            ->with('manager')
            ->withCount(['memberships', 'campaigns', 'productionUnits'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('location', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.farms.index', [
            'farms' => $farms,
            'statuses' => collect(FarmStatus::cases()),
        ]);
    }

    public function show(int $farmId): View
    {
        $farm = Farm::query()
            ->with(['manager', 'memberships.user'])
            ->withCount(['memberships', 'campaigns', 'productionUnits', 'activities', 'harvests'])
            ->findOrFail($farmId);

        return view('admin.farms.show', [
            'farm' => $farm,
            'logs' => AuditLog::query()
                ->forFarm($farm->id)
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function toggleStatus(Request $request, int $farmId): RedirectResponse
    {
        $farm = Farm::query()->findOrFail($farmId);

        if ($farm->isActive()) {
            $farm->update(['status' => FarmStatus::Inactive]);
        } else {
            if ($farm->manager_id === null) {
                return back()->withErrors(['farm' => 'Un responsable doit être désigné avant l’activation.']);
            }

            $farm->update(['status' => FarmStatus::Active]);
        }

        $this->audit->record(
            'admin.farm.status',
            sprintf('Exploitation « %s » %s.', $farm->name, $farm->isActive() ? 'activée' : 'désactivée'),
            $farm,
            userId: $request->user()->id,
            farmId: $farm->id,
        );

        return back()->with('status', 'Statut de l’exploitation mis à jour.');
    }
}
