<?php

namespace App\Http\Middleware;

use App\Support\FarmContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout l'exploitation active à partir d'un rattachement autorisé.
 *
 * L'exploitation n'est jamais lue depuis une valeur fournie par le client :
 * seuls les rattachements actifs de l'utilisateur connecté sont considérés.
 */
class ResolveFarmContext
{
    public function __construct(private FarmContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->is_active) {
            $memberships = $user->memberships()
                ->where('is_active', true)
                ->with('farm')
                ->get()
                ->filter(fn ($membership) => $membership->farm !== null);

            $requestedId = $request->session()->get('active_farm_id');

            $membership = $memberships->firstWhere('farm_id', $requestedId)
                ?? $memberships->first();

            if ($membership !== null) {
                $request->session()->put('active_farm_id', $membership->farm_id);
                $this->context->set($membership->farm, $membership->role);
            }
        }

        return $next($request);
    }
}
