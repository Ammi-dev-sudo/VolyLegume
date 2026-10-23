<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FarmRole;
use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmUser;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Supervision globale des comptes, des rôles de plateforme et des
 * rattachements aux exploitations.
 */
class UserController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
        Gate::authorize('manage', User::class);
    }

    public function index(Request $request): View
    {
        $users = User::query()
            ->withCount('memberships')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status') === 'active'))
            ->when($request->boolean('admins'), fn ($query) => $query->where('is_platform_admin', true))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'farms' => Farm::query()->orderBy('name')->get(),
            'roles' => collect(FarmRole::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_platform_admin' => ['nullable', 'boolean'],
            'farm_id' => ['nullable', Rule::exists('farms', 'id')],
            'role' => ['nullable', Rule::enum(FarmRole::class)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_active' => true,
            'is_platform_admin' => $request->boolean('is_platform_admin'),
        ]);

        if (! empty($validated['farm_id'])) {
            FarmUser::create([
                'farm_id' => $validated['farm_id'],
                'user_id' => $user->id,
                'role' => FarmRole::from($validated['role'] ?? FarmRole::Agent->value),
                'is_active' => true,
            ]);
        }

        $this->audit->record(
            'admin.user.created',
            'Création du compte '.$user->email.' par l’administration de la plateforme.',
            $user,
            ['platform_admin' => $user->isPlatformAdmin(), 'farm_id' => $validated['farm_id'] ?? null],
            userId: $request->user()->id,
        );

        return redirect()->route('admin.users.show', $user)->with('status', 'Compte créé.');
    }

    public function show(User $user): View
    {
        $user->load(['memberships.farm']);

        return view('admin.users.show', [
            'user' => $user,
            'farms' => Farm::query()->orderBy('name')->get(),
            'roles' => collect(FarmRole::cases()),
        ]);
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->audit->record(
            $user->is_active ? 'admin.user.activated' : 'admin.user.deactivated',
            sprintf('Compte %s %s.', $user->email, $user->is_active ? 'réactivé' : 'désactivé'),
            $user,
            userId: $request->user()->id,
        );

        return back()->with('status', 'Statut du compte mis à jour.');
    }

    public function togglePlatformAdmin(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user) && $user->isPlatformAdmin()) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas retirer votre propre rôle d’administrateur de la plateforme.']);
        }

        if ($user->isPlatformAdmin() && User::query()->where('is_platform_admin', true)->count() <= 1) {
            return back()->withErrors(['user' => 'Le dernier administrateur de la plateforme ne peut pas être retiré.']);
        }

        $user->update(['is_platform_admin' => ! $user->isPlatformAdmin()]);

        $this->audit->record(
            'admin.user.platform_role',
            sprintf('Rôle plateforme %s pour %s.', $user->isPlatformAdmin() ? 'accordé' : 'retiré', $user->email),
            $user,
            userId: $request->user()->id,
        );

        return back()->with('status', 'Rôle de plateforme mis à jour.');
    }

    public function attachMembership(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'farm_id' => ['required', Rule::exists('farms', 'id')],
            'role' => ['required', Rule::enum(FarmRole::class)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $membership = FarmUser::updateOrCreate(
            ['farm_id' => $validated['farm_id'], 'user_id' => $user->id],
            ['role' => FarmRole::from($validated['role']), 'is_active' => $request->boolean('is_active', true)],
        );

        $this->audit->record(
            'admin.membership.saved',
            sprintf('Rattachement de %s à l’exploitation #%d (%s).', $user->email, $membership->farm_id, $membership->role->value),
            $membership,
            userId: $request->user()->id,
            farmId: $membership->farm_id,
        );

        return back()->with('status', 'Rattachement enregistré.');
    }

    public function updateMembership(Request $request, User $user, FarmUser $membership): RedirectResponse
    {
        abort_unless((int) $membership->user_id === (int) $user->id, 404);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(FarmRole::class)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = FarmRole::from($validated['role']);
        $isActive = $request->boolean('is_active', true);
        $farm = Farm::query()->findOrFail($membership->farm_id);

        if ((int) $farm->manager_id === (int) $user->id && (! $isActive || $role !== FarmRole::Responsable)) {
            return back()->withErrors([
                'membership' => 'Le responsable principal doit conserver un rattachement actif avec le rôle Responsable.',
            ]);
        }

        $membership->update(['role' => $role, 'is_active' => $isActive]);

        $this->audit->record(
            'admin.membership.updated',
            sprintf('Rôle %s (%s) pour %s sur l’exploitation #%d.', $role->value, $isActive ? 'actif' : 'inactif', $user->email, $membership->farm_id),
            $membership,
            userId: $request->user()->id,
            farmId: $membership->farm_id,
        );

        return back()->with('status', 'Rattachement mis à jour.');
    }
}
