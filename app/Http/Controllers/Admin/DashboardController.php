<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Farm;
use App\Models\Input;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage', User::class);

        return view('admin.dashboard', [
            'counts' => [
                'users' => User::query()->count(),
                'active_users' => User::query()->where('is_active', true)->count(),
                'platform_admins' => User::query()->where('is_platform_admin', true)->count(),
                'farms' => Farm::query()->count(),
                'active_farms' => Farm::query()->where('status', 'active')->count(),
                'campaigns' => Campaign::query()->count(),
                'activities' => Activity::query()->count(),
                'critical_inputs' => Input::query()->whereColumn('quantity', '<', 'minimum_stock')->count(),
            ],
            'recentLogs' => AuditLog::query()
                ->with(['user', 'farm'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get(),
            'recentUsers' => User::query()->orderByDesc('created_at')->limit(6)->get(),
        ]);
    }
}
