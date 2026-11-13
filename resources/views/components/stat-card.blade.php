@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'default',
    'count' => null,
    'decimals' => 0,
    'suffix' => '',
])

<div {{ $attributes->merge(['class' => "stat-card stat-card--{$tone}"]) }} data-reveal>
    <span class="stat-card__label">{{ $label }}</span>
    <span class="stat-card__value"
          @if ($count !== null)
              data-count="{{ $count }}"
              data-count-decimals="{{ $decimals }}"
              data-count-suffix="{{ $suffix }}"
          @endif>
        {{ $value }}
    </span>
    @if ($hint)
        <span class="stat-card__hint">{{ $hint }}</span>
    @endif
</div>
