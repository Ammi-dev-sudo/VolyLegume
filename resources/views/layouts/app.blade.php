@php
    $modulePatterns = [
        'modules.index',
        'crops.*',
        'calendar.*',
        'calendar-steps.*',
        'inputs.*',
        'stock-movements.*',
        'treatments.*',
        'harvests.*',
        'produce-lots.*',
        'sales.*',
        'customers.*',
        'expenses.*',
        'revenues.*',
        'farms.*',
        'team.*',
        'audit.*',
        'resources.*',
        'notifications.*',
        'issues.*',
        'reports.*',
    ];

    $navItems = [
        [
            'route' => 'dashboard',
            'label' => 'Tableau de bord',
            'pattern' => 'dashboard',
            'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.6" /><rect x="14" y="3" width="7" height="7" rx="1.6" /><rect x="3" y="14" width="7" height="7" rx="1.6" /><rect x="14" y="14" width="7" height="7" rx="1.6" />',
        ],
        [
            'route' => 'farm-map.index',
            'label' => 'Plan',
            'pattern' => 'farm-map.*',
            'icon' => '<path d="M4 6.5 9 4l6 2.5L20 4v13.5L15 20l-6-2.5L4 20V6.5Z" /><path d="M9 4v13.5M15 6.5V20" />',
        ],
        [
            'route' => 'production-units.index',
            'label' => 'Parcelles',
            'pattern' => 'production-units.*',
            'icon' => '<path d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Z" /><path d="m3 12 9 4.5 9-4.5" /><path d="m3 16.5 9 4.5 9-4.5" />',
        ],
        [
            'route' => 'campaigns.index',
            'label' => 'Campagnes',
            'pattern' => 'campaigns.*',
            'icon' => '<path d="M5 3v18" /><path d="M5 4h11l-2 3.5L16 11H5" />',
        ],
        [
            'route' => 'issues.index',
            'label' => 'Problèmes',
            'pattern' => 'issues.*',
            'icon' => '<path d="M12 9v4M12 17h.01" /><path d="M10.3 3.9 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />',
        ],
        [
            'route' => 'reports.index',
            'label' => 'Rapports',
            'pattern' => 'reports.*',
            'icon' => '<path d="M6 3h9l3 3v15H6z" /><path d="M9 12h6M9 16h6M9 8h3" />',
        ],
        [
            'route' => 'activities.index',
            'label' => 'Activités',
            'pattern' => 'activities.*',
            'icon' => '<rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 4V3h6v1M9 9h6M9 13h6M9 17h4" />',
        ],
        [
            'route' => 'irrigation.index',
            'label' => 'Irrigation',
            'pattern' => 'irrigation.*',
            'icon' => '<path d="M12 3s5 5.5 5 9a5 5 0 0 1-10 0c0-3.5 5-9 5-9Z" />',
        ],
        [
            'route' => 'calendar.index',
            'label' => 'Agenda',
            'pattern' => 'calendar.*',
            'icon' => '<rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4M16 3v4M3 10h18" />',
        ],
        [
            'route' => 'produce-lots.index',
            'label' => 'Stock récolté',
            'pattern' => 'produce-lots.*',
            'icon' => '<path d="M3 7l9-4 9 4-9 4-9-4Z" /><path d="M3 7v10l9 4 9-4V7" /><path d="M12 11v10" />',
        ],
        [
            'route' => 'modules.index',
            'label' => 'Tout',
            'pattern' => $modulePatterns,
            'icon' => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z" />',
        ],
    ];

    $role = $currentRole ?? null;
    $roleTone = match ($role) {
        \App\Enums\FarmRole::Admin => 'admin',
        \App\Enums\FarmRole::Responsable => 'manager',
        \App\Enums\FarmRole::Agent => 'agent',
        default => 'default',
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="theme-color" content="#f4f6f2" data-light-color="#f4f6f2" data-dark-color="#0a1310">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>{{ config('app.name', 'VolyLegume') }} — @yield('title', 'Gestion maraîchère')</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @include('partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="workspace-body relative min-h-screen overflow-x-hidden">
    <div id="offline-banner" hidden
         class="group fixed inset-x-3 bottom-3 z-50 mx-auto flex max-w-xl items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700 shadow-xl shadow-slate-900/10 data-[state=offline]:border-amber-300 data-[state=offline]:bg-amber-50 data-[state=pending]:border-sky-300 data-[state=pending]:bg-sky-50 data-[state=syncing]:border-sky-300 data-[state=syncing]:bg-sky-50 data-[state=error]:border-red-300 data-[state=error]:bg-red-50 data-[state=success]:border-emerald-300 data-[state=success]:bg-emerald-50">
        <span aria-hidden="true" class="mt-0.5 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-slate-400 group-data-[state=offline]:bg-amber-500 group-data-[state=pending]:bg-sky-500 group-data-[state=syncing]:bg-sky-500 group-data-[state=error]:bg-red-500 group-data-[state=success]:bg-emerald-500"></span>
        <p data-offline-message class="min-w-0 flex-1 leading-snug"></p>
        <button type="button" data-offline-sync hidden
                class="shrink-0 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800">
            Synchroniser
        </button>
        <button type="button" data-offline-clear hidden
                class="shrink-0 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">
            Effacer
        </button>
        <button type="button" data-offline-dismiss aria-label="Masquer le message"
                class="shrink-0 rounded-lg px-2 py-1 text-lg leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-600">
            ×
        </button>
    </div>

    <header class="workspace-header">
        <div class="workspace-header-inner">
            <div class="workspace-brand-group">
                <a href="{{ route('dashboard') }}" class="workspace-brand">
                    <span class="workspace-logo">
                        <img src="{{ asset('images/logo-sm.png') }}" alt="Logo VolyLegume">
                    </span>
                    <span>
                        <span class="workspace-brand-name">VolyLegume</span>
                        <span class="workspace-brand-tag">Gestion maraîchère</span>
                    </span>
                </a>
                @if ($currentFarm)
                    <button type="button" class="workspace-menu-toggle" data-sidebar-toggle
                            aria-controls="workspace-sidebar" aria-expanded="true" aria-label="Masquer le menu"
                            title="Masquer ou afficher le menu">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round" />
                        </svg>
                        <span>Menu</span>
                    </button>
                @endif
            </div>

            <div class="workspace-header-actions">
                @if ($currentFarm)
                    <a href="{{ route('farms.index') }}" class="workspace-farm-switch">
                        <span class="dot" aria-hidden="true"></span>
                        <span class="truncate">{{ $currentFarm->name }}</span>
                        <span class="hint">Changer</span>
                    </a>
                @endif

                @if ($role)
                    <span class="workspace-role-badge workspace-role-badge--{{ $roleTone }}">
                        <span class="dot" aria-hidden="true"></span>
                        {{ $role->label() }}
                    </span>
                @endif

                <x-theme-toggle />

                @if (auth()->user()?->isPlatformAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="workspace-action workspace-action--admin">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9.5 12l1.8 1.8L15 10" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="hidden sm:inline">Administration</span>
                    </a>
                @endif

                <a href="{{ route('notifications.index') }}" class="workspace-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M9 17a3 3 0 0 0 6 0" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="hidden sm:inline">Alertes</span>
                </a>

                <span class="workspace-user-chip">
                    <span class="workspace-avatar" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <span class="hidden max-w-[9rem] truncate sm:inline">{{ auth()->user()->name }}</span>
                </span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="workspace-action workspace-logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="hidden sm:inline">Déconnexion</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="workspace-frame{{ $currentFarm ? '' : ' workspace-frame--solo' }}">
        @if ($currentFarm)
            <button type="button" class="workspace-sidebar-backdrop" data-sidebar-backdrop
                    aria-label="Fermer le menu" hidden></button>
            <aside class="workspace-sidebar" id="workspace-sidebar">
                <p class="workspace-sidebar-label">Espace de travail</p>
                <nav aria-label="Navigation principale" class="workspace-nav">
                    @foreach ($navItems as $item)
                        @php
                            $patterns = (array) $item['pattern'];
                            $active = collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern));
                        @endphp
                        <a href="{{ route($item['route']) }}"
                           @if ($active) aria-current="page" @endif
                           title="{{ $item['label'] }}"
                           data-label="{{ $item['label'] }}"
                           class="workspace-nav-link{{ $active ? ' is-active' : '' }}">
                            <span class="workspace-nav-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    {!! $item['icon'] !!}
                                </svg>
                            </span>
                            <span class="workspace-nav-label">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <a href="{{ route('resources.index') }}" class="workspace-sidebar-note">
                    <span class="workspace-note-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5V5.5Z" />
                            <path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20" />
                        </svg>
                    </span>
                    <span>
                        <span class="block font-semibold">Conseils de culture</span>
                        <span class="mt-1 block text-xs">Guides et ressources terrain</span>
                    </span>
                    <span class="workspace-note-arrow" aria-hidden="true">↗</span>
                </a>
            </aside>
        @endif

        <div class="workspace-content">
            <main class="workspace-main">
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

            <footer class="workspace-footer flex flex-wrap items-center justify-between gap-2">
                <span class="inline-flex items-center gap-2">
                    <img src="{{ asset('images/favicon-32.png') }}" alt="" class="h-5 w-5 rounded">
                    VolyLegume — gestion de l’exploitation maraîchère
                </span>
                <span class="flex items-center gap-3">
                    <a href="{{ route('modules.index') }}">Toutes les fonctions</a>
                    <span aria-hidden="true" class="text-slate-300">·</span>
                    <a href="{{ route('resources.index') }}">Ressources &amp; tutoriels</a>
                </span>
            </footer>
        </div>
    </div>
</body>
</html>
