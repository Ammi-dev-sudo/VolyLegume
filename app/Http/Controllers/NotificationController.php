<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesFarmContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use UsesFarmContext;

    public function index(Request $request): View
    {
        $farmId = $this->context()->id();

        $notifications = $request->user()->notifications()
            ->when($farmId !== null, fn ($query) => $query->where('farm_id', $farmId))
            ->latest()
            ->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $farmId = $this->context()->id();

        $notification = $request->user()->notifications()
            ->when($farmId !== null, fn ($query) => $query->where('farm_id', $farmId))
            ->whereKey($id)
            ->firstOrFail();

        $notification->markAsRead();

        return redirect()->route('notifications.index');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $farmId = $this->context()->id();

        $request->user()->unreadNotifications()
            ->when($farmId !== null, fn ($query) => $query->where('farm_id', $farmId))
            ->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('status', 'Toutes les alertes ont été marquées comme lues.');
    }
}
