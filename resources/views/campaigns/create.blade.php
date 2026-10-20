@extends('layouts.app')

@section('title', 'Nouvelle campagne')

@section('content')
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Nouvelle campagne</h1>

        <x-card>
            <form method="POST" action="{{ route('campaigns.store') }}">
                @csrf
                @include('campaigns._form', ['campaign' => $campaign])

                <div class="mt-4 flex gap-3">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Créer la campagne
                    </button>
                    <a href="{{ route('campaigns.index') }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
