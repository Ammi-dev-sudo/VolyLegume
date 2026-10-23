<?php

namespace App\Enums;

enum ProduceMovementType: string
{
    case Harvest = 'harvest';
    case Sale = 'sale';
    case Loss = 'loss';
    case Gift = 'gift';
    case Consumption = 'consumption';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Harvest => 'Entrée récolte',
            self::Sale => 'Sortie vente',
            self::Loss => 'Perte',
            self::Gift => 'Don',
            self::Consumption => 'Autoconsommation',
            self::Return => 'Retour en stock',
            self::Adjustment => 'Ajustement',
        };
    }

    public function isIncoming(): bool
    {
        return in_array($this, [self::Harvest, self::Return], true);
    }
}
