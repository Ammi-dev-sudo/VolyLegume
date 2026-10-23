<?php

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\RequireActiveFarm;
use App\Http\Middleware\ResolveApiFarmContext;
use App\Http\Middleware\ResolveFarmContext;
use App\Support\DeniedAccessLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveFarmContext::class,
        ]);

        $middleware->throttleApi();

        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveFarmContext::class,
        );

        // Le contexte d'exploitation doit être résolu avant le binding de
        // route : sans cela, `resolveRouteBinding()` compare une exploitation
        // nulle et renvoie un 404 pour toute ressource d'API.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveApiFarmContext::class,
        );

        $middleware->alias([
            'farm' => RequireActiveFarm::class,
            'platform-admin' => EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception): void {
            app(DeniedAccessLogger::class)->report($exception);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
