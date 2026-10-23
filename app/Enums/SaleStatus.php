<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Confirmed => 'Confirmée',
            self::Cancelled => 'Annulée',
        };
    }

    public function isCancellable(): bool
    {
        return $this === self::Confirmed;
    }
}
