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
        ['route' => 'expenses.index', 'label' => 'Dépenses', 'pattern' => 'expenses.*'],
        ['route' => 'revenues.index', 'label' => 'Recettes', 'pattern' => 'revenues.*'],
        ['route' => 'calendar-steps.index', 'label' => 'Modèles calendrier', 'pattern' => 'calendar-steps.*'],
    ];@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'VolyLegume') }} — @yield('title', 'Gestion maraîchère')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <header class="bg-emerald-800 text-white shadow">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight">VolyLegume</a>
            <div class="flex items-center gap-4 text-sm">
                @if ($currentFarm)
                    <span class="hidden sm:inline">Exploitation : <strong>{{ $currentFarm->name }}</strong></span>
                    <a href="{{ route('farms.index') }}" class="underline underline-offset-2">Changer</a>
                @endif
                <a href="{{ route('notifications.index') }}" class="underline underline-offset-2">Alertes</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="underline underline-offset-2">Déconnexion</button>
                </form>
            </div>
        </div>
    </header>

    @if ($currentFarm)
        <nav class="border-b border-emerald-900/10 bg-emerald-700 text-emerald-50">
            <div class="mx-auto flex max-w-7xl flex-wrap gap-x-4 gap-y-1 px-4 py-2 text-sm">
                @foreach ($navItems as $item)
                    @php
                        $patterns = (array) $item['pattern'];
                        $active = collect($patterns)->contains(fn ($pattern) => request()->routeIs($pattern));
                    @endphp
                    <a href="{{ route($item['route']) }}"
                       class="rounded px-2 py-1 {{ $active ? 'bg-emerald-900/40 font-semibold' : 'hover:bg-emerald-900/20' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
                @can('manage-team', $currentFarm)
                    <a href="{{ route('team.index') }}"
                       class="rounded px-2 py-1 {{ request()->routeIs('team.*') ? 'bg-emerald-900/40 font-semibold' : 'hover:bg-emerald-900/20' }}">
                        Équipe
                    </a>
                @endcan
            </div>
        </nav>
    @endif

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-red-900">
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
</body>
</html>
