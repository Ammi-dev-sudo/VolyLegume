<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\ProductionUnit;
use Illuminate\Database\Seeder;

class DemoBoundarySeeder extends Seeder
{
    private const CENTERS = [
        'responsable@volylegume.mg' => [-18.9259831, 47.5577342],
        'responsable.itasy@volylegume.mg' => [-18.9746393, 46.7161791],
    ];

    private const RADIUS = 6378137.0;

    public function run(): void
    {
        Farm::query()->with(['manager', 'productionUnits'])->each(function (Farm $farm): void {
            $center = self::CENTERS[$farm->manager?->email] ?? null;

            if ($center === null) {
                return;
            }

            $units = $farm->productionUnits->keyBy('code');

            foreach (['P01', 'P02', 'P03', 'P04'] as $index => $code) {
                $unit = $units->get($code);

                if ($unit === null || $unit->boundary !== null) {
                    continue;
                }

                $this->applyBoundary($unit, $center[0], $center[1], $index);
            }
        });
    }

    private function applyBoundary(ProductionUnit $unit, float $centerLat, float $centerLng, int $index): void
    {
        $radians = M_PI / 180;
        $cosLatitude = cos($centerLat * $radians);
        $side = sqrt((float) $unit->area_m2);
        $cut = $side * 0.12;
        $centerX = $index % 2 === 0 ? -22.5 : 22.5;
        $centerZ = $index < 2 ? -22.5 : 22.5;
        $x0 = $centerX - $side / 2;
        $x1 = $centerX + $side / 2;
        $z0 = $centerZ - $side / 2;
        $z1 = $centerZ + $side / 2;
        $local = [
            [$x0, $z0],
            [$x1, $z0],
            [$x1, $z1 - $cut],
            [$x1 - $cut, $z1],
            [$x0, $z1],
        ];
        $lngScale = $radians * self::RADIUS * $cosLatitude;
        $latScale = $radians * self::RADIUS;
        $ring = array_map(
            fn (array $point): array => [
                round($centerLng + $point[0] / $lngScale, 10),
                round($centerLat + $point[1] / $latScale, 10),
            ],
            $local,
        );
        $ring[] = $ring[0];
        $centroidLng = $centerLng + ($centerX / $lngScale);
        $centroidLat = $centerLat + ($centerZ / $latScale);

        $unit->forceFill([
            'boundary' => [
                'type' => 'Polygon',
                'coordinates' => [$ring],
            ],
            'latitude' => round($centroidLat, 7),
            'longitude' => round($centroidLng, 7),
        ])->save();
    }
}
