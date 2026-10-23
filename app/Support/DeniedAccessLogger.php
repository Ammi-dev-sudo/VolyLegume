<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Trace les tentatives d'accès refusées (403) et les ressources masquées par
 * l'isolation entre exploitations (404 issu du route binding).
 */
class DeniedAccessLogger
{
    public function __construct(private AuditLogger $audit) {}

    public function report(Throwable $exception): void
    {
        if (! $this->isDenial($exception)) {
            return;
        }

        $request = app()->bound('request') ? request() : null;

        if (! $request instanceof Request || $request->user() === null) {
            return;
        }

        $this->audit->denied(
            'Accès refusé : '.$this->reason($exception, $request),
            [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'status' => $exception instanceof ModelNotFoundException ? 404 : 403,
            ],
        );
    }

    private function isDenial(Throwable $exception): bool
    {
        return $exception instanceof AuthorizationException
            || $exception instanceof AccessDeniedHttpException
            || $exception instanceof ModelNotFoundException;
    }

    private function reason(Throwable $exception, Request $request): string
    {
        if ($exception instanceof ModelNotFoundException) {
            return 'ressource introuvable ou hors de l’exploitation active ('.$request->path().')';
        }

        return $exception->getMessage() !== ''
            ? $exception->getMessage()
            : $request->path();
    }
}
