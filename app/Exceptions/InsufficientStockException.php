<?php

namespace App\Exceptions;

use App\Models\Input;
use RuntimeException;

/**
 * RG05 : aucun mouvement sortant accepté ne rend le stock disponible négatif.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly Input $input,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(sprintf(
            'Stock insuffisant pour « %s » : disponible %s %s, demandé %s %s.',
            $input->name,
            $available,
            $input->base_unit->value,
            abs($requested),
            $input->base_unit->value,
        ));
    }
}
