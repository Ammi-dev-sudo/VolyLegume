<?php

namespace App\Enums;

enum ReferenceType: string
{
    case Sowing = 'sowing';
    case Planting = 'planting';

    public function label(): string
    {
        return match ($this) {
            self::Sowing => 'Semis',
            self::Planting => 'Plantation',
        };
    }
}
