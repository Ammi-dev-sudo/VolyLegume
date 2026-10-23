<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve le panneau de supervision aux administrateurs de la plateforme.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403, 'Accès réservé à l’administration de la plateforme.');

        return $next($request);
    }
}
