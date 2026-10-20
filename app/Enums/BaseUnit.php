<?php

namespace App\Enums;

enum BaseUnit: string
{
    case Kg = 'kg';
    case Liter = 'L';
    case Piece = 'piece';

    public function label(): string
    {
        return match ($this) {
            self::Kg => 'kilogramme (kg)',
            self::Liter => 'litre (L)',
            self::Piece => 'pièce',
        };
    }
}
