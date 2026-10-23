<?php

namespace App\Enums;

enum CustomerType: string
{
    case Individual = 'individual';
    case Retailer = 'retailer';
    case Restaurant = 'restaurant';
    case Hotel = 'hotel';
    case Cooperative = 'cooperative';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Particulier',
            self::Retailer => 'Commerçant / détaillant',
            self::Restaurant => 'Restaurant',
            self::Hotel => 'Hôtel',
            self::Cooperative => 'Coopérative',
            self::Other => 'Autre',
        };
    }
}
