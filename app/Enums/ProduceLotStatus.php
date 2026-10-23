<?php

namespace App\Enums;

enum ProduceLotStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Depleted = 'depleted';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Reserved => 'Réservé',
            self::Depleted => 'Épuisé',
            self::Lost => 'Perdu',
        };
    }
}
