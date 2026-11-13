<?php

namespace App\Enums;

enum IssueType: string
{
    case Disease = 'disease';
    case Pest = 'pest';
    case Fungus = 'fungus';
    case NutrientDeficiency = 'nutrient_deficiency';
    case Irrigation = 'irrigation';
    case Drought = 'drought';
    case ExcessWater = 'excess_water';
    case PoorGrowth = 'poor_growth';
    case SoilDegradation = 'soil_degradation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Disease => 'Maladie',
            self::Pest => 'Insecte nuisible',
            self::Fungus => 'Champignon',
            self::NutrientDeficiency => 'Carence nutritionnelle',
            self::Irrigation => 'Problème d’irrigation',
            self::Drought => 'Sécheresse',
            self::ExcessWater => 'Excès d’eau',
            self::PoorGrowth => 'Mauvaise croissance',
            self::SoilDegradation => 'Dégradation du sol',
            self::Other => 'Autre problème',
        };
    }
}
