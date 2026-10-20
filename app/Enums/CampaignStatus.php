<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Suspended = 'suspended';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planifiée',
            self::Ongoing => 'En cours',
            self::Suspended => 'Suspendue',
            self::Completed => 'Terminée',
            self::Cancelled => 'Annulée',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Planned, self::Ongoing], true);
    }
}
