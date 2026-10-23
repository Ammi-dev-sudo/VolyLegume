<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Non payée',
            self::Partial => 'Partiellement payée',
            self::Paid => 'Payée',
            self::Refunded => 'Remboursée',
        };
    }
}
