<?php

namespace App\Enums;

enum FarmRole: string
{
    case Admin = 'admin';
    case Responsable = 'responsable';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Responsable => 'Responsable',
            self::Agent => 'Agent / technicien',
        };
    }

    public function canManageFinances(): bool
    {
        return $this !== self::Agent;
    }

    public function canManageReferentials(): bool
    {
        return $this !== self::Agent;
    }

    public function canManageTeam(): bool
    {
        return $this !== self::Agent;
    }
}
