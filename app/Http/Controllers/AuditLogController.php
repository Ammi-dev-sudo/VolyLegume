<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Journal d'audit d'une exploitation : accessible aux responsables et aux
 * administrateurs de l'exploitation active.
 */
class AuditLogController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        $farmId = $this->farmId();

        $logs = AuditLog::query()
            ->forFarm($farmId)
            ->with('user')
            ->search($request->string('search')->toString())
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->forFarm($farmId)->distinct()->orderBy('action')->pluck('action'),
            'members' => User::query()
                ->whereHas('memberships', fn ($query) => $query->where('farm_id', $farmId))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
