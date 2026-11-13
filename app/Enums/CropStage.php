<?php

namespace App\Enums;

enum CropStage: string
{
    case SoilPreparation = 'soil_preparation';
    case Sowing = 'sowing';
    case Germination = 'germination';
    case Growth = 'growth';
    case Transplanting = 'transplanting';
    case Maintenance = 'maintenance';
    case Flowering = 'flowering';
    case Harvest = 'harvest';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::SoilPreparation => 'Préparation du terrain',
            self::Sowing => 'Semis',
            self::Germination => 'Germination',
            self::Growth => 'Croissance',
            self::Transplanting => 'Transplantation',
            self::Maintenance => 'Entretien',
            self::Flowering => 'Floraison',
            self::Harvest => 'Récolte',
            self::Completed => 'Fin du cycle',
        };
    }

    public function position(): int
    {
        return match ($this) {
            self::SoilPreparation => 1,
            self::Sowing => 2,
            self::Germination => 3,
            self::Growth => 4,
            self::Transplanting => 5,
            self::Maintenance => 6,
            self::Flowering => 7,
            self::Harvest => 8,
            self::Completed => 9,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SoilPreparation => 'amber',
            self::Sowing => 'lime',
            self::Germination => 'emerald',
            self::Growth => 'emerald',
            self::Transplanting => 'teal',
            self::Maintenance => 'sky',
            self::Flowering => 'violet',
            self::Harvest => 'orange',
            self::Completed => 'slate',
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::SoilPreparation,
            self::Sowing,
            self::Germination,
            self::Growth,
            self::Transplanting,
            self::Maintenance,
            self::Flowering,
            self::Harvest,
            self::Completed,
        ];
    }
}
