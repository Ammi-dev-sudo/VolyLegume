<?php

namespace App\Http\Controllers;

use App\Enums\FarmRole;
use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\FarmUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gestion des rattachements (association APPARTENIR du MCD).
 *
 * Le rôle et l'état du rattachement appartiennent à `farm_user` ; les comptes
 * historiques ne sont jamais supprimés, seulement désactivés (C09).
 */
class TeamController extends Controller
{
    use UsesFarmContext;

    public function index(): View
    {
        $farm = $this->farm();

        Gate::authorize('manage-team', $farm);

        return view('farms.team', [
            'farm' => $farm,
            'memberships' => FarmUser::query()
                ->where('farm_id', $farm->id)
                ->with('user')
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->get(),
            'roles' => FarmRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $farm = $this->farm();

        Gate::authorize('manage-team', $farm);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'role' => ['required', Rule::enum(FarmRole::class)],
        ], [
            'email.exists' => 'Aucun compte actif ne correspond à cette adresse.',
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return back()->withErrors(['email' => 'Aucun compte actif ne correspond à cette adresse.']);
        }

        $existing = FarmUser::query()
            ->where('farm_id', $farm->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null && $existing->is_active) {
            return back()->withErrors(['email' => 'Ce compte est déjà rattaché à l’exploitation.']);
        }

        if ($existing !== null) {
            $existing->update(['role' => FarmRole::from($validated['role']), 'is_active' => true]);

            return redirect()->route('team.index')->with('status', "Rattachement de {$user->name} réactivé.");
        }

        FarmUser::create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'role' => FarmRole::from($validated['role']),
            'is_active' => true,
        ]);

        return redirect()->route('team.index')->with('status', "{$user->name} rattaché à l’exploitation.");
    }

    public function update(Request $request, FarmUser $farmUser): RedirectResponse
    {
        $farm = $this->farm();

        abort_unless((int) $farmUser->farm_id === $farm->id, 404);

        Gate::authorize('manage-team', $farm);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(FarmRole::class)],
        ]);

        if ((int) $farm->manager_id === (int) $farmUser->user_id
            && FarmRole::from($validated['role']) !== FarmRole::Responsable) {
            return back()->withErrors([
                'role' => 'Le responsable principal conserve le rôle Responsable ; changez d’abord le responsable de l’exploitation.',
            ]);
        }

        $farmUser->update(['role' => FarmRole::from($validated['role'])]);

        return redirect()->route('team.index')->with('status', 'Rôle mis à jour.');
    }

    public function destroy(Request $request, FarmUser $farmUser): RedirectResponse
    {
        $farm = $this->farm();

        abort_unless((int) $farmUser->farm_id === $farm->id, 404);

        Gate::authorize('manage-team', $farm);

        if ((int) $farm->manager_id === (int) $farmUser->user_id) {
            return back()->withErrors([
                'membership' => 'Le responsable principal ne peut pas être désactivé ; changez d’abord le responsable de l’exploitation.',
            ]);
        }

        if ((int) $request->user()->id === (int) $farmUser->user_id) {
            return back()->withErrors([
                'membership' => 'Vous ne pouvez pas désactiver votre propre rattachement.',
            ]);
        }

        $farmUser->update(['is_active' => false]);

        return redirect()->route('team.index')->with('status', 'Rattachement désactivé.');
    }
}
