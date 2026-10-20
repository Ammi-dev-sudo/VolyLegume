<?php

namespace App\Events;

use App\Models\Input;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis après le passage validé d'un intrant sous son seuil minimal.
 */
class StockCritical
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Input $input,
        public float $quantity,
    ) {}
}
