<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Inputs = 'inputs';
    case Labour = 'labour';
    case Treatment = 'treatment';
    case Transport = 'transport';
    case Energy = 'energy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Inputs => 'Intrants',
            self::Labour => 'Main-d’œuvre',
            self::Treatment => 'Traitement',
            self::Transport => 'Transport',
            self::Energy => 'Énergie',
            self::Other => 'Autre',
        };
    }
}
