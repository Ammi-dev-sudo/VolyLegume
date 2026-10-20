@extends('layouts.app')

@section('title', 'Nouvel intrant')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Nouvel intrant</h1>

        <x-card>
            <form method="POST" action="{{ route('inputs.store') }}">
                @csrf
                @include('inputs._form', ['input' => $input, 'withInitialStock' => true])

                <div class="mt-4 flex gap-3">
                    <button type="submit"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Enregistrer
                    </button>
                    <a href="{{ route('inputs.index') }}"
                       class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
