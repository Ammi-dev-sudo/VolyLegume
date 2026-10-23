@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'spotlight-card rounded-xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md']) }} data-reveal data-spotlight>
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
