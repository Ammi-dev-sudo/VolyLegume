<?php

namespace App\Support;

/**
 * Normalisation et calculs sur les contours parcellaires (GeoJSON Polygon).
 *
 * Les coordonnées sont stockées dans l'ordre GeoJSON [longitude, latitude].
 * Les surfaces sont calculées sur une projection locale équirectangulaire,
 * suffisante à l'échelle d'une exploitation agricole.
 */
final class ParcelBoundary
{
    private const EARTH_RADIUS = 6378137.0;

    private const MAX_POINTS = 2000;

    /**
     * @return array{type: string, coordinates: array<int, array<int, array{0: float, 1: float}>>}|null
     */
    public static function normalize(mixed $value): ?array
    {
        $data = $value;

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }

            $data = json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
        }

        if (! is_array($data)) {
            return null;
        }

        if (self::isBareRing($data)) {
            $data = ['type' => 'Polygon', 'coordinates' => [$data]];
        }

        if (($data['type'] ?? null) !== 'Polygon') {
            return null;
        }

        $ring = $data['coordinates'][0] ?? null;

        if (! is_array($ring) || count($ring) < 3 || count($ring) > self::MAX_POINTS + 1) {
            return null;
        }

        $points = [];

        foreach ($ring as $point) {
            if (! is_array($point) || count($point) < 2) {
                return null;
            }

            $lng = $point[0] ?? null;
            $lat = $point[1] ?? null;

            if (! is_numeric($lng) || ! is_numeric($lat)) {
                return null;
            }

            $lng = (float) $lng;
            $lat = (float) $lat;

            if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) {
                return null;
            }

            $last = $points === [] ? null : $points[array_key_last($points)];

            if ($last !== null && self::samePoint($last, [$lng, $lat])) {
                continue;
            }

            $points[] = [$lng, $lat];
        }

        if (count($points) > 1 && self::samePoint($points[0], $points[array_key_last($points)])) {
            array_pop($points);
        }

        if (count($points) < 3 || count($points) > self::MAX_POINTS) {
            return null;
        }

        if (self::areaM2($points) <= 0.0001) {
            return null;
        }

        return self::toGeoJson($points);
    }

    /**
     * @param  array<string, mixed>|null  $geojson
     * @return list<array{0: float, 1: float}>
     */
    public static function points(?array $geojson): array
    {
        $ring = $geojson['coordinates'][0] ?? null;

        if (! is_array($ring)) {
            return [];
        }

        $points = [];

        foreach ($ring as $point) {
            if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                continue;
            }

            $points[] = [(float) $point[0], (float) $point[1]];
        }

        if (count($points) > 1 && self::samePoint($points[0], $points[array_key_last($points)])) {
            array_pop($points);
        }

        return $points;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return array{type: string, coordinates: array<int, array<int, array{0: float, 1: float}>>}
     */
    public static function toGeoJson(array $points): array
    {
        $ring = array_map(
            fn (array $point): array => [(float) $point[0], (float) $point[1]],
            array_values($points),
        );

        if (count($ring) > 2) {
            $ring[] = $ring[0];
        }

        return ['type' => 'Polygon', 'coordinates' => [$ring]];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    public static function areaM2(array $points): float
    {
        $count = count($points);

        if ($count < 3) {
            return 0.0;
        }

        $projected = self::project($points);

        $sum = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $current = $projected[$index];
            $next = $projected[($index + 1) % $count];
            $sum += $current[0] * $next[1] - $next[0] * $current[1];
        }

        return abs($sum) / 2;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    public static function perimeterM(array $points): float
    {
        $count = count($points);

        if ($count < 2) {
            return 0.0;
        }

        $perimeter = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $perimeter += self::distanceM($points[$index], $points[($index + 1) % $count]);
        }

        return $perimeter;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return array{lat: float, lng: float}|null
     */
    public static function centroid(array $points): ?array
    {
        $count = count($points);

        if ($count < 3) {
            return null;
        }

        $projected = self::project($points);
        $areaSum = 0.0;
        $xSum = 0.0;
        $ySum = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $current = $projected[$index];
            $next = $projected[($index + 1) % $count];
            $cross = $current[0] * $next[1] - $next[0] * $current[1];
            $areaSum += $cross;
            $xSum += ($current[0] + $next[0]) * $cross;
            $ySum += ($current[1] + $next[1]) * $cross;
        }

        if (abs($areaSum) < 1e-9) {
            $lat = array_sum(array_column($points, 1)) / $count;
            $lng = array_sum(array_column($points, 0)) / $count;

            return ['lat' => $lat, 'lng' => $lng];
        }

        $x = $xSum / (3 * $areaSum);
        $y = $ySum / (3 * $areaSum);
        $latOrigin = self::meanLatitude($points);

        return [
            'lat' => rad2deg($y / self::EARTH_RADIUS),
            'lng' => rad2deg($x / (self::EARTH_RADIUS * cos($latOrigin))),
        ];
    }

    /**
     * Projette les coordonnées en mètres autour d'une origine géographique.
     *
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<array{x: float, z: float}>
     */
    public static function toLocalMeters(array $points, float $originLat, float $originLng): array
    {
        $cosLatitude = cos(deg2rad($originLat));

        return array_map(fn (array $point): array => [
            'x' => deg2rad($point[0] - $originLng) * $cosLatitude * self::EARTH_RADIUS,
            'z' => deg2rad($point[1] - $originLat) * self::EARTH_RADIUS,
        ], $points);
    }

    /**
     * @param  array<int, mixed>  $data
     */
    private static function isBareRing(array $data): bool
    {
        if (! array_is_list($data) || $data === []) {
            return false;
        }

        $first = $data[0] ?? null;

        return is_array($first)
            && array_is_list($first)
            && count($first) >= 2
            && is_numeric($first[0] ?? null)
            && is_numeric($first[1] ?? null);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     * @return list<array{0: float, 1: float}>
     */
    private static function project(array $points): array
    {
        $latOrigin = self::meanLatitude($points);
        $cosLatitude = cos($latOrigin);

        return array_map(fn (array $point): array => [
            deg2rad($point[0]) * $cosLatitude * self::EARTH_RADIUS,
            deg2rad($point[1]) * self::EARTH_RADIUS,
        ], $points);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $points
     */
    private static function meanLatitude(array $points): float
    {
        return deg2rad(array_sum(array_column($points, 1)) / count($points));
    }

    /**
     * @param  array{0: float, 1: float}  $first
     * @param  array{0: float, 1: float}  $second
     */
    private static function samePoint(array $first, array $second): bool
    {
        return abs($first[0] - $second[0]) < 1e-10
            && abs($first[1] - $second[1]) < 1e-10;
    }

    /**
     * @param  array{0: float, 1: float}  $first
     * @param  array{0: float, 1: float}  $second
     */
    private static function distanceM(array $first, array $second): float
    {
        $lat1 = deg2rad($first[1]);
        $lat2 = deg2rad($second[1]);
        $deltaLat = $lat2 - $lat1;
        $deltaLng = deg2rad($second[0] - $first[0]);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
