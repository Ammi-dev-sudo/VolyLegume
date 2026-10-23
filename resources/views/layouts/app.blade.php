@php
    $navItems = [
        ['route' => 'dashboard', 'label' => 'Tableau de bord', 'pattern' => 'dashboard'],
        ['route' => 'production-units.index', 'label' => 'Parcelles', 'pattern' => 'production-units.*'],
        ['route' => 'crops.index', 'label' => 'Cultures', 'pattern' => 'crops.*'],
        ['route' => 'campaigns.index', 'label' => 'Campagnes', 'pattern' => 'campaigns.*'],
        ['route' => 'activities.index', 'label' => 'Activités', 'pattern' => 'activities.*'],
        ['route' => 'inputs.index', 'label' => 'Intrants & stocks', 'pattern' => ['inputs.*', 'stock-movements.*']],
        ['route' => 'treatments.index', 'label' => 'Traitements', 'pattern' => 'treatments.*'],
        ['route' => 'harvests.index', 'label' => 'Récoltes', 'pattern' => 'harvests.*'],
        ['route' => 'produce-lots.index', 'label' => 'Stock récolté', 'pattern' => 'produce-lots.*'],
        ['route' => 'sales.index', 'label' => 'Ventes', 'pattern' => 'sales.*'],
        ['route' => 'customers.index', 'label' => 'Clients', 'pattern' => 'customers.*'],
        ['route' => 'expenses.index', 'label' => 'Dépenses', 'pattern' => 'expenses.*'],
        ['route' => 'revenues.index', 'label' => 'Recettes', 'pattern' => 'revenues.*'],
        ['route' => 'calendar-steps.index', 'label' => 'Modèles de calendrier', 'pattern' => 'calendar-steps.*'],
    ];

    $role = $currentRole ?? null;
    $roleBadge = match ($role) {
        \App\Enums\FarmRole::Admin => ['bg-amber-300/20 text-amber-100 ring-amber-200/40', 'bg-amber-400'],
        \App\Enums\FarmRole::Responsable => ['bg-emerald-300/20 text-emerald-50 ring-emerald-200/40', 'bg-emerald-300'],
        \App\Enums\FarmRole::Agent => ['bg-sky-300/20 text-sky-50 ring-sky-200/40', 'bg-sky-300'],
        default => ['bg-white/10 text-white ring-white/20', 'bg-white/60'],
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#065f46">
    <title>{{ config('app.name', 'VolyLegume') }} — @yield('title', 'Gestion maraîchère')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative min-h-screen overflow-x-hidden bg-slate-50 text-slate-800">
    <div aria-hidden="true" class="pointer-events-none fixed -left-40 top-24 -z-10 h-96 w-96 rounded-full bg-emerald-300/20 blur-3xl animate-blob"></div>
    <div aria-hidden="true" class="pointer-events-none fixed -right-32 bottom-0 -z-10 h-96 w-96 rounded-full bg-amber-200/30 blur-3xl animate-blob" style="animation-delay: -7s"></div>

    <header class="sticky top-0 z-40 animate-fade-in-down border-b border-emerald-950/20 bg-gradient-to-r from-emerald-950 via-emerald-800 to-emerald-700 text-white shadow-lg shadow-emerald-950/20">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-2.5">
            <a href="{{ route('dashboard') }}" class="group flex items-center gap-3 rounded-xl px-1 py-1 transition hover:bg-white/5">
                <span class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-white/95 p-1 shadow-md ring-1 ring-white/30 transition duration-300 group-hover:scale-105 group-hover:rotate-2">
                    <img src="{{ asset('images/logo-sm.png') }}" alt="Logo VolyLegume" class="h-full w-full object-contain">
                    <span aria-hidden="true" class="absolute inset-0 rounded-xl ring-1 ring-inset ring-emerald-900/10"></span>
                </span>
                <span class="leading-tight">
                    <span class="block text-lg font-bold tracking-tight">VolyLegume</span>
                    <span class="block text-[11px] uppercase tracking-[0.18em] text-emerald-200/80">Gestion maraîchère</span>
                </span>
            </a>

            <div class="flex flex-wrap items-center gap-2 text-sm sm:gap-3">
                @if ($currentFarm)
                    <a href="{{ route('farms.index') }}"
                       class="group hidden items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15 transition hover:bg-white/15 sm:inline-flex">
                        <span class="h-2 w-2 rounded-full bg-emerald-300 shadow-[0_0_0_4px_rgba(110,231,183,0.15)] animate-pulse-soft"></span>
                        <span class="max-w-[12rem] truncate">{{ $currentFarm->name }}</span>
                        <span class="text-xs text-emerald-100/70 group-hover:text-white">Changer</span>
                    </a>
                @endif

                @if ($role)
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $roleBadge[0] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $roleBadge[1] }}"></span>
                        {{ $role->label() }}
                    </span>
                @endif

                @if (auth()->user()?->isPlatformAdmin())
                    <a href="{{ route('admin.dashboard') }}"
                       class="inline-flex items-center gap-1.5 rounded-full bg-amber-300/20 px-3 py-1.5 text-amber-50 ring-1 ring-amber-200/40 transition hover:bg-amber-300/30">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9.5 12l1.8 1.8L15 10" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="hidden sm:inline">Administration</span>
                    </a>
                @endif

                <a href="{{ route('notifications.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 ring-1 ring-white/10 transition hover:bg-white/10 hover:ring-white/25">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M9 17a3 3 0 0 0 6 0" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="hidden sm:inline">Alertes</span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 ring-1 ring-white/10 transition hover:bg-red-500/20 hover:ring-red-300/40">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="hidden sm:inline">Déconnexion</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    @if ($currentFarm)
        <nav class="animate-fade-in border-b border-emerald-900/10 bg-emerald-700/95 text-emerald-50 shadow-sm backdrop-blur">
            <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 py-2 text-sm [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($navItems as $item)
                    @php
                        $patterns = (array) $item['pattern'];
                        $active = collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern));
                    @endphp
                    <a href="{{ route($item['route']) }}"
                       @if ($active) aria-current="page" @endif
                       class="nav-link shrink-0 rounded-full px-3 py-1.5 whitespace-nowrap transition duration-200 {{ $active ? 'bg-emerald-950/35 font-semibold shadow-inner' : 'hover:-translate-y-0.5 hover:bg-emerald-950/20' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
                @can('manage-team', $currentFarm)
                    <a href="{{ route('team.index') }}"
                       @if (request()->routeIs('team.*')) aria-current="page" @endif
                       class="nav-link shrink-0 rounded-full px-3 py-1.5 whitespace-nowrap transition duration-200 {{ request()->routeIs('team.*') ? 'bg-emerald-950/35 font-semibold shadow-inner' : 'hover:-translate-y-0.5 hover:bg-emerald-950/20' }}">
                        Équipe
                    </a>
                @endcan
                @if ($currentFarm && Gate::allows('viewAny', App\Models\AuditLog::class))
                    <a href="{{ route('audit.index') }}"
                       @if (request()->routeIs('audit.index')) aria-current="page" @endif
                       class="nav-link shrink-0 rounded-full px-3 py-1.5 whitespace-nowrap transition duration-200 {{ request()->routeIs('audit.index') ? 'bg-emerald-950/35 font-semibold shadow-inner' : 'hover:-translate-y-0.5 hover:bg-emerald-950/20' }}">
                        Journal
                    </a>
                @endif
            </div>
        </nav>
    @endif

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 flex items-start gap-3 animate-slide-in-right rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-900 shadow-sm">
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">✓</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 animate-slide-in-right rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-red-900 shadow-sm">
                <p class="font-semibold">Corrigez les erreurs suivantes :</p>
                <ul class="mt-1 list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 pb-6 pt-2 text-xs text-slate-400">
        <span class="inline-flex items-center gap-2">
            <img src="{{ asset('images/favicon-32.png') }}" alt="" class="h-5 w-5 rounded">
            VolyLegume — gestion de l’exploitation maraîchère
        </span>
        <a href="{{ route('resources.index') }}" class="text-emerald-600 transition hover:text-emerald-800">Ressources &amp; tutoriels</a>
    </footer>
</body>
</html>
