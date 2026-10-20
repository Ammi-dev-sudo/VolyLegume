@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'rounded border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title)
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="font-semibold text-slate-800">{{ $title }}</h2>
            @if ($subtitle)
                <p class="text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
    @endif
    <div class="p-4">{{ $slot }}</div>
</div>
