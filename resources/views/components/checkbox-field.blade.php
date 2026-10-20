@props(['name', 'label', 'value' => 1, 'hint' => null, 'checked' => null])

<div {{ $attributes->merge(['class' => 'block']) }}>
    <label class="flex items-start gap-2 text-sm">
        <input
            type="checkbox"
            name="{{ $name }}"
            value="{{ $value }}"
            @checked($checked !== null ? $checked : old($name))
            class="mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
        >
        <span>{{ $label }}</span>
    </label>
    @if ($hint)
        <p class="ml-6 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="ml-6 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
