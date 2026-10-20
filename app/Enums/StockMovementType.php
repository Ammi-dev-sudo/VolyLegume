<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Initial = 'initial';
    case Entry = 'entry';
    case Exit = 'exit';
    case Consumption = 'consumption';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Stock initial',
            self::Entry => 'Entrée',
            self::Exit => 'Sortie',
            self::Consumption => 'Consommation',
            self::Adjustment => 'Ajustement',
        };
    }

    public function isIncoming(): bool
    {
        return in_array($this, [self::Initial, self::Entry], true);
    }
}
