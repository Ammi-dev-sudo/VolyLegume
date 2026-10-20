<?php

namespace App\Providers;

use App\Enums\FarmRole;
use App\Models\Farm;
use App\Models\User;
use App\Support\FarmContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(FarmContext::class, fn () => new FarmContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-finances', function (User $user): bool {
            return app(FarmContext::class)->hasRole(FarmRole::Admin, FarmRole::Responsable);
        });

        Gate::define('manage-referentials', function (User $user): bool {
            return app(FarmContext::class)->hasRole(FarmRole::Admin, FarmRole::Responsable);
        });

        Gate::define('manage-team', function (User $user, Farm $farm): bool {
            return in_array($farm->roleOf($user), [FarmRole::Admin, FarmRole::Responsable], true);
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip(),
            );
        });

        View::composer('*', function ($view): void {
            $view->with('currentFarm', app(FarmContext::class)->farm());
        });
    }
}
