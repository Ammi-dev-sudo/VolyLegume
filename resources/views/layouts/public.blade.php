<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#154b32" data-light-color="#154b32" data-dark-color="#0a1310">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>{{ config('app.name', 'VolyLegume') }} — @yield('title', 'Traçabilité')</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @include('partials.theme-init')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="trace-body min-h-screen bg-slate-50 text-slate-800">
    <header class="trace-header bg-gradient-to-r from-emerald-950 via-emerald-800 to-emerald-700 text-white shadow-lg">
        <div class="mx-auto flex max-w-4xl items-center gap-3 px-4 py-3">
            <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-white/95 p-1">
                <img src="{{ asset('images/logo-sm.png') }}" alt="Logo VolyLegume" class="h-full w-full object-contain">
            </span>
            <div class="leading-tight">
                <p class="text-base font-bold tracking-tight">VolyLegume</p>
                <p class="text-[11px] uppercase tracking-[0.18em] text-emerald-200/80">Traçabilité des récoltes</p>
            </div>
            <div class="ml-auto">
                <x-theme-toggle variant="on-dark" />
            </div>
        </div>
    </header>

    <main class="trace-main mx-auto max-w-4xl px-4 py-8">
        @yield('content')
    </main>

    <footer class="trace-footer border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-4xl px-4 py-4 text-xs text-slate-500">
            Données déclaratives issues de la gestion de l’exploitation. Cette page ne remplace pas une certification officielle.
        </div>
    </footer>
</body>
</html>
