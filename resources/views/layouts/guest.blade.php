<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'VolyLegume') }} — @yield('title', 'Connexion')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-emerald-900 p-4 text-slate-800">
    <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-xl">
        <h1 class="mb-1 text-2xl font-bold text-emerald-800">VolyLegume</h1>
        <p class="mb-6 text-sm text-slate-500">Gestion de l’exploitation maraîchère</p>

        @if (session('status'))
            <div class="mb-4 rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
