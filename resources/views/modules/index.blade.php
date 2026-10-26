@extends('layouts.app')

@section('title', 'Toutes les fonctions')

@section('content')
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-emerald-950 to-teal-800 px-5 py-6 text-white shadow-xl shadow-emerald-950/20 animate-fade-in-down sm:px-8 sm:py-8">
        <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-emerald-400/20 blur-3xl animate-blob"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/4 h-56 w-56 rounded-full bg-sky-300/15 blur-3xl animate-blob" style="animation-delay: -9s"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid opacity-20"></div>

        <div class="relative max-w-3xl">
            <span class="inline-flex animate-fade-in-up items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] ring-1 ring-white/20">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse-soft"></span>
                Toutes les fonctions
            </span>
            <h1 class="mt-4 animate-fade-in-up text-3xl font-bold leading-tight sm:text-4xl" style="animation-delay: 80ms">
                Le reste de <span class="gradient-text">{{ $farm->name }}</span>, sans surcharger le menu
            </h1>
            <p class="mt-3 animate-fade-in-up text-sm leading-relaxed text-emerald-50/80" style="animation-delay: 150ms">
                Chaque fonctionnalité reste accessible depuis ce hub. Les raccourcis du menu principal couvrent
                les gestes du quotidien ; retrouvez ici les référentiels, le commercial, les finances et
                l’administration de l’exploitation.
            </p>
        </div>
    </section>

    <div class="mt-6 space-y-8" data-reveal-group="50">
        @foreach ($groups as $group)
            <section>
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800">{{ $group['title'] }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ $group['description'] }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500">
                        {{ count($group['items']) }} fonction(s)
                    </span>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($group['items'] as $item)
                        <a href="{{ route($item['route']) }}"
                           class="group relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
                            <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 origin-left scale-x-0 bg-gradient-to-r from-emerald-500 to-teal-400 transition-transform duration-300 group-hover:scale-x-100"></span>
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-semibold text-slate-800 transition group-hover:text-emerald-800">{{ $item['title'] }}</h3>
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition group-hover:bg-emerald-50 group-hover:text-emerald-700">
                                    <svg class="h-3.5 w-3.5 transition-transform duration-200 group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                        <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                            </div>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $item['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
        <p class="text-slate-500">Besoin d’un coup de main sur une fonction ?</p>
        <a href="{{ route('resources.index') }}" class="font-semibold text-emerald-700 transition hover:text-emerald-800">
            Ouvrir les ressources &amp; tutoriels &rarr;
        </a>
    </div>
@endsection
