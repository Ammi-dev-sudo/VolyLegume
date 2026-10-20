<?php

namespace App\Listeners;

use App\Events\StockCritical;
use App\Jobs\NotifyStockCritical;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Réagit au passage en stock critique en programmant une notification différée.
 */
class SendStockCriticalNotification implements ShouldQueue
{
    public function handle(StockCritical $event): void
    {
        NotifyStockCritical::dispatch($event->input);
    }
}
