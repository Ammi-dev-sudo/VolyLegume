@extends('layouts.app')

@section('title', 'Ressources & tutoriels')

@php
    $accentStyles = [
        'emerald' => ['thumb' => 'from-emerald-500 via-emerald-600 to-teal-700', 'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'dot' => 'bg-emerald-500'],
        'lime' => ['thumb' => 'from-lime-500 via-green-600 to-emerald-700', 'badge' => 'bg-lime-50 text-lime-700 ring-lime-200', 'dot' => 'bg-lime-500'],
        'amber' => ['thumb' => 'from-amber-400 via-orange-500 to-amber-700', 'badge' => 'bg-amber-50 text-amber-700 ring-amber-200', 'dot' => 'bg-amber-500'],
        'rose' => ['thumb' => 'from-rose-500 via-pink-600 to-rose-800', 'badge' => 'bg-rose-50 text-rose-700 ring-rose-200', 'dot' => 'bg-rose-500'],
        'sky' => ['thumb' => 'from-sky-500 via-blue-600 to-indigo-700', 'badge' => 'bg-sky-50 text-sky-700 ring-sky-200', 'dot' => 'bg-sky-500'],
        'indigo' => ['thumb' => 'from-indigo-500 via-violet-600 to-purple-700', 'badge' => 'bg-indigo-50 text-indigo-700 ring-indigo-200', 'dot' => 'bg-indigo-500'],
        'gray' => ['thumb' => 'from-slate-500 via-slate-600 to-slate-800', 'badge' => 'bg-slate-100 text-slate-700 ring-slate-200', 'dot' => 'bg-slate-500'],
    ];
@endphp

@section('content')
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-emerald-800 to-teal-700 px-6 py-8 text-white shadow-xl shadow-emerald-950/20 animate-fade-in-down sm:px-10 sm:py-10">
        <div aria-hidden="true" class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-emerald-400/20 blur-3xl animate-blob"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 left-1/3 h-64 w-64 rounded-full bg-amber-300/20 blur-3xl animate-blob" style="animation-delay: -8s"></div>
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid opacity-30"></div>

        <div class="relative grid items-center gap-8 lg:grid-cols-[1.4fr_0.6fr]">
            <div>
                <span class="inline-flex animate-fade-in-up items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] ring-1 ring-white/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse-soft"></span>
                    Centre d’apprentissage
                </span>
                <h1 class="mt-4 animate-fade-in-up text-3xl font-bold leading-tight sm:text-4xl" style="animation-delay: 80ms">
                    Ressources &amp; <span class="gradient-text">tutoriels</span>
                </h1>
                <p class="mt-3 max-w-2xl animate-fade-in-up text-sm leading-relaxed text-emerald-50/80" style="animation-delay: 150ms">
                    Des vidéos courtes, des guides pas à pas et un rappel clair des rôles pour
                    tirer le meilleur de VolyLegume et de votre exploitation.
                </p>

                <div class="mt-5 flex animate-fade-in-up flex-wrap gap-3" style="animation-delay: 220ms">
                    <a href="#videos"
                       class="btn-shine inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-emerald-800 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M8 5.5v13l11-6.5-11-6.5Z" />
                        </svg>
                        Voir les tutoriels vidéo
                    </a>
                    <a href="#guides"
                       class="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:-translate-y-0.5 hover:bg-white/15">
                        Guides pas à pas
                    </a>
                </div>
            </div>

            <div class="relative mx-auto hidden w-full max-w-[16rem] lg:block">
                <div class="absolute inset-6 rounded-full bg-emerald-400/25 blur-2xl animate-pulse-soft"></div>
                <img src="{{ asset('images/logo.png') }}"
                     alt="Logo VolyLegume aux légumes frais"
                     class="float-slow relative w-full drop-shadow-2xl">
            </div>
        </div>
    </section>

    <section id="videos" class="mt-8 scroll-mt-24">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Tutoriels vidéo</h2>
                <p class="text-sm text-slate-500">Sélection de recherches vidéo ciblées, à regarder au moment où vous en avez besoin.</p>
            </div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                {{ count($videos) }} thèmes
            </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-reveal-group="60">
            @foreach ($videos as $video)
                @php
                    $accent = $accentStyles[$video['accent']] ?? $accentStyles['emerald'];
                @endphp
                <a href="{{ $video['url'] }}" target="_blank" rel="noopener noreferrer"
                   class="resource-card group relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                   data-reveal data-tilt data-spotlight>
                    <div class="relative h-36 overflow-hidden bg-gradient-to-br {{ $accent['thumb'] }}">
                        <div aria-hidden="true" class="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-white/15 blur-xl"></div>
                        <div aria-hidden="true" class="absolute -bottom-10 -left-6 h-24 w-24 rounded-full bg-black/10 blur-lg"></div>
                        <svg class="resource-thumb absolute inset-0 m-auto h-14 w-14 text-white/90 drop-shadow-lg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M8 5.5v13l11-6.5-11-6.5Z" />
                        </svg>
                        <span class="absolute right-3 top-3 rounded-full bg-black/30 px-2.5 py-1 text-[11px] font-semibold text-white backdrop-blur">
                            {{ $video['duration'] }}
                        </span>
                    </div>
                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 {{ $accent['badge'] }}">
                                {{ $video['level'] }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 opacity-0 transition group-hover:opacity-100">
                                Regarder
                                <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                        <h3 class="mt-2 font-semibold text-slate-800 transition group-hover:text-emerald-800">{{ $video['title'] }}</h3>
                        <p class="mt-1 flex-1 text-sm leading-relaxed text-slate-500">{{ $video['description'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section id="guides" class="mt-10 scroll-mt-24">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Guides pas à pas</h2>
                <p class="text-sm text-slate-500">Accédez directement à l’écran concerné dans votre exploitation.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                {{ count($guides) }} guides
            </span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-reveal-group="70">
            @foreach ($guides as $guide)
                <a href="{{ route($guide['route']) }}"
                   class="resource-card group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                   data-reveal data-tilt data-spotlight>
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-sm font-bold text-emerald-700 ring-1 ring-emerald-100 transition group-hover:bg-emerald-600 group-hover:text-white">
                        {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <span class="min-w-0">
                        <span class="block font-semibold text-slate-800 transition group-hover:text-emerald-800">{{ $guide['title'] }}</span>
                        <span class="mt-0.5 block text-sm leading-relaxed text-slate-500">{{ $guide['description'] }}</span>
                        <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-emerald-700">
                            {{ $guide['cta'] }}
                            <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section id="roles" class="mt-10 scroll-mt-24">
        <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-800">Rôles &amp; permissions</h2>
            <p class="text-sm text-slate-500">Chaque membre possède un rôle dans l’exploitation active. Le vôtre est mis en évidence.</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3" data-reveal-group="80">
            @foreach ($roles as $roleCard)
                @php
                    $isCurrent = ($currentRole ?? null) === $roleCard['role'];
                    $accent = $accentStyles[$roleCard['accent']] ?? $accentStyles['gray'];
                @endphp
                <div class="relative flex animate-fade-in-up flex-col rounded-2xl border bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-md {{ $isCurrent ? 'border-emerald-400 ring-2 ring-emerald-200' : 'border-slate-200' }}"
                     style="animation-delay: {{ $loop->index * 80 }}ms">
                    @if ($isCurrent)
                        <span class="absolute -top-3 right-4 rounded-full bg-emerald-600 px-3 py-0.5 text-[11px] font-semibold text-white shadow">
                            Votre rôle
                        </span>
                    @endif
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full {{ $accent['dot'] }}"></span>
                        <h3 class="font-semibold text-slate-800">{{ $roleCard['role']->label() }}</h3>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $roleCard['description'] }}</p>

                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($roleCard['permissions'] as $permission)
                            <li class="flex items-start gap-2 {{ $permission['allowed'] ? 'text-slate-700' : 'text-slate-400' }}">
                                @if ($permission['allowed'])
                                    <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                            <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                @else
                                    <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                @endif
                                {{ $permission['label'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 text-sm text-emerald-900">
            Les rôles se gèrent dans <a href="{{ $currentFarm ? route('team.index') : route('farms.index') }}" class="font-semibold underline underline-offset-2 hover:text-emerald-700">l’équipe de l’exploitation</a>.
            Un administrateur ou un responsable peut rattacher un compte et modifier son rôle.
        </div>
    </section>
@endsection
