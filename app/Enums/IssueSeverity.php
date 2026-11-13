<?php

namespace App\Enums;

enum IssueSeverity: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Faible',
            self::Moderate => 'Modéré',
            self::High => 'Élevé',
            self::Critical => 'Critique',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Moderate => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }
}
