@php
    $rolePresentation = [
        \App\Enums\FarmRole::Admin->value => [
            'text' => 'Accès complet : exploitation, équipe, finances et référentiels.',
            'dot' => 'bg-amber-300',
        ],
        \App\Enums\FarmRole::Responsable->value => [
            'text' => 'Pilotage de la production, des finances et de l’équipe terrain.',
            'dot' => 'bg-emerald-300',
        ],
        \App\Enums\FarmRole::Agent->value => [
            'text' => 'Saisie des activités, traitements, récoltes et mouvements de stock.',
            'dot' => 'bg-sky-300',
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#022c22">
    <title>{{ config('app.name', 'VolyLegume') }} — @yield('title', 'Connexion')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative min-h-screen overflow-x-hidden bg-emerald-950 text-slate-800">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-gradient-to-br from-emerald-950 via-emerald-900 to-teal-800"></div>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-grid opacity-40"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -left-24 top-10 h-[26rem] w-[26rem] rounded-full bg-emerald-400/30 blur-3xl animate-blob"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -right-20 top-1/3 h-[24rem] w-[24rem] rounded-full bg-lime-300/25 blur-3xl animate-blob" style="animation-delay: -6s"></div>
    <div aria-hidden="true" class="pointer-events-none absolute bottom-[-10rem] left-1/3 h-[22rem] w-[22rem] rounded-full bg-amber-300/20 blur-3xl animate-blob" style="animation-delay: -11s"></div>

    <div class="relative z-10 mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-8 sm:px-6">
        <div class="grid w-full items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
            <section class="hidden text-white lg:block">
                <a href="{{ url('/') }}" class="group inline-flex items-center gap-4">
                    <span class="logo-halo animate-bounce-in flex h-24 w-24 items-center justify-center overflow-hidden rounded-3xl bg-white p-2 shadow-2xl shadow-emerald-950/40 ring-1 ring-white/40 transition duration-500 group-hover:rotate-3 group-hover:scale-105">
                        <img src="{{ asset('images/logo.png') }}" alt="Logo VolyLegume aux légumes frais" class="h-full w-full object-contain">
                    </span>
                    <span>
                        <span class="block text-4xl font-bold tracking-tight">VolyLegume</span>
                        <span class="mt-1 block text-xs uppercase tracking-[0.35em] text-emerald-200/80">Maraîchage connecté</span>
                    </span>
                </a>

                <h1 class="mt-8 max-w-xl animate-fade-in-up text-3xl font-semibold leading-tight" style="animation-delay: 120ms">
                    Cultivez mieux,
                    <span class="gradient-text">pilotez sereinement.</span>
                </h1>
                <p class="mt-4 max-w-lg animate-fade-in-up text-sm leading-relaxed text-emerald-50/80" style="animation-delay: 200ms">
                    Parcelles, cultures, calendrier, stocks, traitements, récoltes et finances :
                    toute votre exploitation maraîchère réunie dans un espace clair et vivant.
                </p>

                <ul class="mt-6 space-y-2.5 text-sm text-emerald-50/90">
                    @foreach ([
                        'Un tableau de bord qui se met à jour selon vos filtres.',
                        'Des rôles clairs pour chaque membre de l’équipe.',
                        'Des tutoriels et fiches pratiques intégrés.',
                    ] as $feature)
                        <li class="flex animate-slide-in-left items-center gap-3" style="animation-delay: {{ 280 + $loop->index * 90 }}ms">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-400/20 text-emerald-200 ring-1 ring-emerald-300/30">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                                    <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>

                <div class="mt-8 animate-fade-in-up" style="animation-delay: 620ms">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200/70">Rôles disponibles</p>
                    <div class="mt-3 space-y-2">
                        @foreach (\App\Enums\FarmRole::cases() as $farmRole)
                            <div class="flex items-start gap-3 rounded-xl bg-white/5 px-3 py-2 ring-1 ring-white/10 backdrop-blur-sm">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $rolePresentation[$farmRole->value]['dot'] }}"></span>
                                <div>
                                    <p class="text-sm font-semibold">{{ $farmRole->label() }}</p>
                                    <p class="text-xs text-emerald-50/65">{{ $rolePresentation[$farmRole->value]['text'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <div class="animate-scale-in w-full rounded-3xl bg-white/95 p-6 shadow-2xl shadow-emerald-950/40 ring-1 ring-white/50 backdrop-blur sm:p-8">
                <div class="mb-6 flex items-center gap-3 lg:hidden">
                    <span class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-emerald-50 p-1.5 ring-1 ring-emerald-100">
                        <img src="{{ asset('images/logo.png') }}" alt="Logo VolyLegume" class="h-full w-full object-contain">
                    </span>
                    <span>
                        <span class="block text-xl font-bold tracking-tight text-emerald-900">VolyLegume</span>
                        <span class="block text-[11px] uppercase tracking-[0.2em] text-emerald-600/70">Gestion maraîchère</span>
                    </span>
                </div>

                <div class="mb-6">
                    <h2 class="animate-fade-in-up text-2xl font-bold text-slate-800">@yield('title', 'Connexion')</h2>
                    <p class="mt-1 animate-fade-in-up text-sm text-slate-500" style="animation-delay: 70ms">
                        Accédez à votre exploitation en quelques secondes.
                    </p>
                </div>

                @if (session('status'))
                    <div class="mb-4 animate-slide-in-right rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 animate-slide-in-right rounded-xl border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
