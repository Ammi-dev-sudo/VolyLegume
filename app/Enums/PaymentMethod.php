<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case BankTransfer = 'bank_transfer';
    case Check = 'check';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Espèces',
            self::MobileMoney => 'Mobile money',
            self::BankTransfer => 'Virement bancaire',
            self::Check => 'Chèque',
            self::Other => 'Autre',
        };
    }
}
