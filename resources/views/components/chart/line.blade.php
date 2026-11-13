@props([
    'points' => [],
    'height' => 210,
    'suffix' => '',
    'decimals' => 0,
    'color' => 'accent',
    'emptyLabel' => 'Aucune donnée sur la période.',
    'ariaLabel' => 'Évolution sur douze mois',
])

@php
    $points = array_values($points);
    $values = collect($points)->map(fn (array $point): float => (float) ($point['value'] ?? 0));
    $max = max(1.0, (float) ($values->max() ?? 0));
    $count = count($points);
    $width = 640;
    $plotLeft = 48;
    $plotRight = 16;
    $plotTop = 16;
    $plotBottom = 36;
    $plotWidth = $width - $plotLeft - $plotRight;
    $plotHeight = (int) $height - $plotTop - $plotBottom;
    $format = fn ($value): string => number_format((float) $value, (int) $decimals, ',', ' ');
    $hasValues = $values->sum() > 0;

    $coordinates = [];
    foreach ($points as $index => $point) {
        $x = $count > 1
            ? $plotLeft + ($index / ($count - 1)) * $plotWidth
            : $plotLeft + $plotWidth / 2;
        $y = $plotTop + $plotHeight - (((float) ($point['value'] ?? 0)) / $max) * $plotHeight;
        $coordinates[] = ['x' => round($x, 2), 'y' => round($y, 2), 'point' => $point];
    }

    $linePath = collect($coordinates)
        ->map(fn (array $coordinate, int $index): string => ($index === 0 ? 'M' : 'L').' '.$coordinate['x'].' '.$coordinate['y'])
        ->implode(' ');

    $firstX = $coordinates[0]['x'] ?? $plotLeft;
    $lastX = $coordinates[count($coordinates) - 1]['x'] ?? ($width - $plotRight);
    $baseline = $plotTop + $plotHeight;
    $areaPath = $linePath.' L '.$lastX.' '.$baseline.' L '.$firstX.' '.$baseline.' Z';
@endphp

<div class="chart-shell">
    @if (! $hasValues)
        <p class="chart-empty">{{ $emptyLabel }}</p>
    @else
        <svg viewBox="0 0 {{ $width }} {{ (int) $height }}" class="chart-svg" role="img" aria-label="{{ $ariaLabel }}">
            @for ($tick = 0; $tick <= 4; $tick++)
                @php
                    $tickValue = $max * ($tick / 4);
                    $tickY = $plotTop + $plotHeight - ($tick / 4) * $plotHeight;
                @endphp
                <line x1="{{ $plotLeft }}" x2="{{ $width - $plotRight }}" y1="{{ $tickY }}" y2="{{ $tickY }}" class="chart-grid" />
                <text x="{{ $plotLeft - 8 }}" y="{{ $tickY + 4 }}" text-anchor="end" class="chart-axis">{{ $format($tickValue) }}</text>
            @endfor

            <path d="{{ $areaPath }}" class="chart-area chart-area--{{ $color }}" />
            <path d="{{ $linePath }}" class="chart-line chart-line--{{ $color }}" />

            @foreach ($coordinates as $coordinate)
                <circle cx="{{ $coordinate['x'] }}" cy="{{ $coordinate['y'] }}" r="3.2"
                        class="chart-point chart-point--{{ $color }}">
                    <title>{{ $coordinate['point']['full_label'] ?? $coordinate['point']['label'] ?? '' }} : {{ $format($coordinate['point']['value'] ?? 0) }}{{ $suffix }}</title>
                </circle>
            @endforeach

            @foreach ($points as $index => $point)
                @php
                    $x = $count > 1
                        ? $plotLeft + ($index / ($count - 1)) * $plotWidth
                        : $plotLeft + $plotWidth / 2;
                @endphp
                <text x="{{ round($x, 2) }}" y="{{ (int) $height - 12 }}" text-anchor="middle" class="chart-axis">
                    {{ $point['label'] ?? '' }}
                </text>
            @endforeach
        </svg>
    @endif
</div>
