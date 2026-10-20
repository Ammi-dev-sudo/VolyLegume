@props(['name', 'label', 'type' => 'text', 'value' => null, 'placeholder' => null, 'hint' => null, 'required' => false])

@php
    $value = old($name, $value);
@endphp

<div class="block">
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}@if ($required) <span class="text-red-600">*</span>@endif
    </label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        {{ $attributes->merge(['class' => 'mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600']) }}
    >
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
