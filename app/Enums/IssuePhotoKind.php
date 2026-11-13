<?php

namespace App\Enums;

enum IssuePhotoKind: string
{
    case Observation = 'observation';
    case Before = 'before';
    case Treatment = 'treatment';
    case After = 'after';

    public function label(): string
    {
        return match ($this) {
            self::Observation => 'Observation',
            self::Before => 'Avant intervention',
            self::Treatment => 'Pendant le traitement',
            self::After => 'Après intervention',
        };
    }
}
