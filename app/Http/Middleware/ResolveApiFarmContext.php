<?php

namespace App\Http\Middleware;

use App\Support\FarmContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout l'exploitation active pour les requêtes API.
 *
 * L'exploitation vient toujours d'un rattachement actif du compte. L'entête
 * optionnelle `X-Farm-Id` permet de choisir parmi ses exploitations ; toute
 * valeur non autorisée répond 404 sans confirmer l'existence de la ressource.
 */
class ResolveApiFarmContext
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

            $requestedId = (int) $request->header('X-Farm-Id', 0);

            if ($requestedId > 0) {
                abort_if(
                    $memberships->firstWhere('farm_id', $requestedId) === null,
                    404,
                    'Exploitation inconnue ou accès refusé.',
                );
            }

            $membership = $memberships->firstWhere('farm_id', $requestedId)
                ?? $memberships->first();

            if ($membership !== null) {
                $this->context->set($membership->farm, $membership->role);
            }
        }

        return $next($request);
    }
}
