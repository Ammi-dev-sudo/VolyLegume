<?php

namespace App\Enums;

enum IssueStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::InProgress => 'En traitement',
            self::Monitoring => 'Sous surveillance',
            self::Resolved => 'Résolu',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }
}
