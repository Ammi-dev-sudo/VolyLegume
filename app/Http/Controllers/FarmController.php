<?php

namespace App\Http\Controllers;

use App\Enums\FarmRole;
use App\Enums\FarmStatus;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Support\FarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmController extends Controller
{
    public function index(Request $request): View
    {
        $memberships = $request->user()->memberships()
            ->with('farm')
            ->where('is_active', true)
            ->get();

        return view('farms.index', [
            'memberships' => $memberships,
            'activeFarmId' => app(FarmContext::class)->id(),
        ]);
    }

    public function create(): View
    {
        return view('farms.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'location' => ['required', 'string', 'max:255'],
            'area_m2' => ['required', 'numeric', 'gt:0'],
        ]);

        $user = $request->user();

        $farm = DB::transaction(function () use ($validated, $user) {
            $farm = Farm::create([
                'name' => $validated['name'],
                'location' => $validated['location'],
                'type' => 'market_gardening',
                'area_m2' => $validated['area_m2'],
                'manager_id' => $user->id,
                'status' => FarmStatus::Draft,
                'currency' => 'MGA',
            ]);

            FarmUser::create([
                'farm_id' => $farm->id,
                'user_id' => $user->id,
                'role' => FarmRole::Responsable,
                'is_active' => true,
            ]);

            $farm->update(['status' => FarmStatus::Active]);

            return $farm;
        });

        $request->session()->put('active_farm_id', $farm->id);

        return redirect()->route('dashboard')
            ->with('status', "Exploitation « {$farm->name} » créée et activée.");
    }

    public function edit(Farm $farm): View
    {
        Gate::authorize('update', $farm);

        return view('farms.edit', [
            'farm' => $farm,
            'statuses' => collect(FarmStatus::cases()),
            'managers' => $farm->memberships()
                ->where('is_active', true)
                ->where('role', FarmRole::Responsable->value)
                ->with('user')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function update(Request $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('update', $farm);

        $activating = $request->string('status')->toString() === FarmStatus::Active->value;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'location' => ['required', 'string', 'max:255'],
            'area_m2' => ['required', 'numeric', 'gt:0'],
            'status' => ['required', Rule::enum(FarmStatus::class)],
            'manager_id' => [
                Rule::requiredIf($activating),
                'nullable',
                Rule::exists('farm_user', 'user_id')
                    ->where('farm_id', $farm->id)
                    ->where('is_active', true)
                    ->where('role', FarmRole::Responsable->value),
            ],
        ], [
            'manager_id.required' => 'Un responsable actif est requis pour activer l’exploitation.',
            'manager_id.exists' => 'Le responsable doit être un membre actif avec le rôle Responsable.',
        ]);

        $farm->update($validated);

        return redirect()->route('farms.index')
            ->with('status', "Exploitation « {$farm->name} » mise à jour.");
    }

    public function switch(Request $request, Farm $farm): RedirectResponse
    {
        $request->session()->put('active_farm_id', $farm->id);

        return redirect()->route('dashboard')
            ->with('status', "Exploitation active : {$farm->name}.");
    }
}
