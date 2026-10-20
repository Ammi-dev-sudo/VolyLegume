<?php

namespace App\Enums;

enum RevenueCategory: string
{
    case HarvestSale = 'harvest_sale';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HarvestSale => 'Vente de récolte',
            self::Other => 'Autre',
        };
    }
}
