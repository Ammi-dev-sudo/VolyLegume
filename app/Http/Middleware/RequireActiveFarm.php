<?php

namespace App\Http\Middleware;

use App\Support\FarmContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige une exploitation active avant d'accéder aux routes métier.
 */
class RequireActiveFarm
{
    public function __construct(private FarmContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->farm() === null) {
            if ($request->is('api/*') || $request->expectsJson()) {
                abort(403, 'Aucune exploitation active pour ce compte.');
            }

            return redirect()
                ->route('farms.create')
                ->with('status', 'Créez ou rejoignez une exploitation pour accéder à cette page.');
        }

        return $next($request);
    }
}
