@extends('layouts.app')

@section('title', 'Modifier l’intrant')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Modifier « {{ $input->name }} »</h1>

        <x-card>
            <form method="POST" action="{{ route('inputs.update', $input) }}">
                @csrf
                @method('PUT')
                @include('inputs._form')

                <div class="mt-4 flex gap-3">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Mettre à jour
                    </button>
                    <a href="{{ route('inputs.show', $input) }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
