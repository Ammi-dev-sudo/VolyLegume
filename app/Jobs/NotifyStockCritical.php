<?php

namespace App\Jobs;

use App\Enums\FarmRole;
use App\Models\Input;
use App\Notifications\StockCriticalNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Notifie les responsables de l'exploitation concernée (canal base).
 */
class NotifyStockCritical implements ShouldQueue
{
    use Queueable;

    public function __construct(public Input $input) {}

    public function handle(): void
    {
        $farm = $this->input->farm()->first();

        if ($farm === null) {
            return;
        }

        $recipients = $farm->memberships()
            ->where('is_active', true)
            ->whereIn('role', [FarmRole::Admin->value, FarmRole::Responsable->value])
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn ($user) => $user !== null && $user->is_active);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new StockCriticalNotification($this->input));

        Log::info('Alerte de stock critique envoyée', [
            'farm_id' => $farm->id,
            'input_id' => $this->input->id,
            'recipients' => $recipients->count(),
        ]);
    }
}
