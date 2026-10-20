<?php

namespace App\Support;

use App\Models\Farm;
use Illuminate\Support\Facades\Cache;

/**
 * Versionnement des agrégats mis en cache : le tableau de bord utilise une clé
 * qui dépend d'un compteur incrémenté après chaque écriture métier.
 */
class DashboardCache
{
    public static function version(Farm $farm): int
    {
        return (int) Cache::rememberForever(self::versionKey($farm), fn () => 1);
    }

    public static function forget(Farm $farm): void
    {
        Cache::forever(self::versionKey($farm), self::version($farm) + 1);
    }

    private static function versionKey(Farm $farm): string
    {
        return "dashboard.farm.{$farm->id}.version";
    }
}
