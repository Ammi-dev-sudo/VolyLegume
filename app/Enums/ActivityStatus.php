<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Planned = 'planned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Prévue',
            self::Completed => 'Réalisée',
            self::Cancelled => 'Annulée',
        };
    }
}
