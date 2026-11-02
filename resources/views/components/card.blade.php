@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'spotlight-card rounded-2xl border border-slate-200 bg-white shadow-sm']) }} data-reveal data-spotlight>
    @if ($title)
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-800">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
    @endif
    <div class="p-5">{{ $slot }}</div>
</div>
