@extends('layouts.app')

@section('title', 'Mes exploitations')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-slate-800">Mes exploitations</h1>
        <a href="{{ route('farms.create') }}"
           class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            Créer une exploitation
        </a>
    </div>

    @if ($memberships->isEmpty())
        <x-card>
            <p class="text-sm text-slate-600">
                Vous n’appartenez à aucune exploitation. Créez une exploitation ou demandez
                à un responsable de vous rattacher.
            </p>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($memberships as $membership)
                <x-card>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="font-semibold text-slate-800">{{ $membership->farm->name }}</h2>
                            <p class="text-sm text-slate-500">{{ $membership->farm->location }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                Rôle : {{ $membership->role->label() }}
                                · {{ number_format((float) $membership->farm->area_m2, 0, ',', ' ') }} m²
                            </p>
                        </div>
                        @if ((int) $activeFarmId === (int) $membership->farm_id)
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                        @endif
                    </div>

                    @if ((int) $activeFarmId !== (int) $membership->farm_id)
                        <form method="POST" action="{{ route('farms.switch', $membership->farm) }}" class="mt-4">
                            @csrf
                            <button type="submit"
                                    class="w-full rounded border border-emerald-700 px-3 py-1.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                                Activer cette exploitation
                            </button>
                        </form>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
