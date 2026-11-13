@props([
    'items' => [],
    'unit' => 'm²',
    'emptyLabel' => 'Aucune culture active.',
    'ariaLabel' => 'Répartition des surfaces par culture',
])

@php
    $palette = ['accent', 'gold', 'info', 'danger', 'muted'];
    $items = collect($items)
        ->map(fn (array $item): array => [
            'label' => (string) ($item['crop'] ?? '—'),
            'value' => (float) ($item['area_m2'] ?? 0),
        ])
        ->filter(fn (array $item): bool => $item['value'] > 0)
        ->sortByDesc('value')
        ->values();

    if ($items->count() > 6) {
        $head = $items->take(5);
        $rest = $items->slice(5)->sum('value');
        $items = $head->push(['label' => 'Autres', 'value' => $rest])->values();
    }

    $items = $items->map(fn (array $item, int $index): array => $item + ['color' => $palette[$index % count($palette)]]);
    $total = (float) $items->sum('value');
    $radius = 60;
    $circumference = 2 * M_PI * $radius;
    $offset = 0.0;
    $format = fn ($value): string => number_format((float) $value, 0, ',', ' ');
@endphp

<div class="chart-shell">
    @if ($total <= 0)
        <p class="chart-empty">{{ $emptyLabel }}</p>
    @else
        <div class="donut-layout">
            <svg viewBox="0 0 160 160" class="donut-svg" role="img" aria-label="{{ $ariaLabel }}">
                <circle cx="80" cy="80" r="{{ $radius }}" class="donut-track" />
                @foreach ($items as $item)
                    @php
                        $length = ($item['value'] / $total) * $circumference;
                        $gap = $circumference - $length;
                    @endphp
                    <circle cx="80" cy="80" r="{{ $radius }}"
                            class="donut-segment donut-segment--{{ $item['color'] }}"
                            stroke-dasharray="{{ round($length, 2) }} {{ round($gap, 2) }}"
                            stroke-dashoffset="{{ round(-$offset, 2) }}"
                            transform="rotate(-90 80 80)">
                        <title>{{ $item['label'] }} : {{ $format($item['value']) }} {{ $unit }}</title>
                    </circle>
                    @php $offset += $length; @endphp
                @endforeach
                <text x="80" y="76" text-anchor="middle" class="donut-total">{{ $format($total) }}</text>
                <text x="80" y="94" text-anchor="middle" class="donut-unit">{{ $unit }}</text>
            </svg>

            <ul class="donut-legend">
                @foreach ($items as $item)
                    <li>
                        <i class="chart-dot chart-dot--{{ $item['color'] }}" aria-hidden="true"></i>
                        <span class="donut-legend__label">{{ $item['label'] }}</span>
                        <span class="donut-legend__value">{{ $format($item['value']) }} {{ $unit }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
