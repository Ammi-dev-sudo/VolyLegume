@props(['name', 'label', 'rows' => 4, 'value' => null, 'placeholder' => null, 'required' => false])

@php
    $value = old($name, $value);
@endphp

<div {{ $attributes->merge(['class' => 'block']) }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}@if ($required) <span class="text-red-600">*</span>@endif
    </label>
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
    >{{ $value }}</textarea>
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
