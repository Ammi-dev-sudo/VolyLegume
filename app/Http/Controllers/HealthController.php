<?php

namespace App\Http\Controllers;

use App\Services\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint de supervision disponible pour les sondes de production.
 *
 * Si HEALTH_TOKEN est défini, l'en-tête X-Health-Token doit correspondre.
 * Dans le cas contraire l'endpoint reste public mais ne renvoie que des états
 * techniques, jamais de données métier.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request, HealthService $health): JsonResponse
    {
        $expected = (string) config('health.token', '');

        if ($expected !== '' && ! hash_equals($expected, (string) $request->header('X-Health-Token', ''))) {
            abort(404);
        }

        $result = $health->check();

        return response()->json(
            $result,
            $result['status'] === 'down' ? 503 : 200,
        );
    }
}
