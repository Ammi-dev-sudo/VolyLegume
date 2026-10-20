<?php

namespace App\Enums;

enum ProductionUnitStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Resting = 'resting';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Occupied => 'Occupée',
            self::Resting => 'En repos',
            self::Inactive => 'Inactive',
        };
    }
}
