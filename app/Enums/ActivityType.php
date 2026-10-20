<?php

namespace App\Enums;

enum ActivityType: string
{
    case SoilPreparation = 'soil_preparation';
    case Sowing = 'sowing';
    case Transplanting = 'transplanting';
    case Fertilization = 'fertilization';
    case Treatment = 'treatment';
    case Irrigation = 'irrigation';
    case Weeding = 'weeding';
    case Harvest = 'harvest';
    case Observation = 'observation';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::SoilPreparation => 'Préparation du sol',
            self::Sowing => 'Semis',
            self::Transplanting => 'Repiquage',
            self::Fertilization => 'Fertilisation',
            self::Treatment => 'Traitement phytosanitaire',
            self::Irrigation => 'Irrigation',
            self::Weeding => 'Désherbage',
            self::Harvest => 'Récolte',
            self::Observation => 'Observation',
            self::Maintenance => 'Entretien',
        };
    }
}
