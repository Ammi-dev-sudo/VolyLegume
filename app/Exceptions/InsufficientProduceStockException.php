<?php

namespace App\Exceptions;

use App\Models\ProduceLot;
use RuntimeException;

class InsufficientProduceStockException extends RuntimeException
{
    public function __construct(
        public readonly ProduceLot $lot,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(sprintf(
            'Stock insuffisant pour le lot « %s » : disponible %s %s, demandé %s %s.',
            $lot->code,
            $available,
            $lot->unit,
            $requested,
            $lot->unit,
        ));
    }
}
