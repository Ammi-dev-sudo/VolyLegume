@props([
    'labels' => [],
    'series' => [],
    'height' => 230,
    'suffix' => '',
    'decimals' => 0,
    'emptyLabel' => 'Aucune donnée sur la période.',
    'ariaLabel' => 'Graphique en barres',
])

@php
    $labels = array_values($labels);
    $series = array_values($series);
    $values = collect($series)->flatMap(fn (array $item): array => $item['values'] ?? [])->map(fn ($value): float => (float) $value);
    $max = max(1.0, (float) ($values->max() ?? 0));
    $labelCount = max(1, count($labels));
    $seriesCount = max(1, count($series));
    $width = 640;
    $plotLeft = 48;
    $plotRight = 14;
    $plotTop = 16;
    $plotBottom = 36;
    $plotWidth = $width - $plotLeft - $plotRight;
    $plotHeight = (int) $height - $plotTop - $plotBottom;
    $groupWidth = $plotWidth / $labelCount;
    $barWidth = min(24.0, ($groupWidth * 0.68) / $seriesCount);
    $barGap = 2.0;
    $groupInner = $barWidth * $seriesCount + $barGap * ($seriesCount - 1);
    $format = fn ($value): string => number_format((float) $value, (int) $decimals, ',', ' ');
    $hasValues = $values->sum() > 0;
@endphp

<div class="chart-shell">
    <div class="chart-legend">
        @foreach ($series as $item)
            <span class="chart-legend__item">
                <i class="chart-dot chart-dot--{{ $item['color'] ?? 'accent' }}" aria-hidden="true"></i>
                {{ $item['name'] }}
            </span>
        @endforeach
    </div>

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

            @foreach ($labels as $index => $label)
                @php
                    $groupX = $plotLeft + $index * $groupWidth;
                    $innerX = $groupX + ($groupWidth - $groupInner) / 2;
                @endphp

                @foreach ($series as $seriesIndex => $item)
                    @php
                        $value = (float) ($item['values'][$index] ?? 0);
                        $barHeight = max(0.0, ($value / $max) * $plotHeight);
                        $barX = $innerX + $seriesIndex * ($barWidth + $barGap);
                        $barY = $plotTop + $plotHeight - $barHeight;
                    @endphp
                    <rect x="{{ round($barX, 2) }}" y="{{ round($barY, 2) }}"
                          width="{{ round($barWidth, 2) }}" height="{{ round($barHeight, 2) }}"
                          rx="3" class="chart-bar chart-bar--{{ $item['color'] ?? 'accent' }}">
                        <title>{{ $item['name'] }} · {{ $label }} : {{ $format($value) }}{{ $suffix }}</title>
                    </rect>
                @endforeach

                <text x="{{ round($groupX + $groupWidth / 2, 2) }}" y="{{ (int) $height - 12 }}"
                      text-anchor="middle" class="chart-axis">{{ $label }}</text>
            @endforeach
        </svg>
    @endif
</div>
