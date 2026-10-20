<?php

namespace App\Enums;

enum InputCategory: string
{
    case Seed = 'seed';
    case Fertilizer = 'fertilizer';
    case Phytosanitary = 'phytosanitary';
    case Fuel = 'fuel';
    case Consumable = 'consumable';

    public function label(): string
    {
        return match ($this) {
            self::Seed => 'Semence',
            self::Fertilizer => 'Engrais',
            self::Phytosanitary => 'Produit phytosanitaire',
            self::Fuel => 'Carburant',
            self::Consumable => 'Consommable',
        };
    }

    public function requiresPreHarvestDelay(): bool
    {
        return $this === self::Phytosanitary;
    }
}
