@extends('layouts.app')

@section('title', 'Modifier le problème')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-4">
            <a href="{{ route('issues.show', $issue) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">&larr; Fiche du problème</a>
            <h1 class="mt-2 text-xl font-semibold text-slate-800">Modifier {{ $issue->reference }}</h1>
            <p class="text-sm text-slate-500">{{ $issue->title }}</p>
        </div>

        <x-card>
            <form method="POST" action="{{ route('issues.update', $issue) }}">
                @csrf
                @method('PUT')
                @include('issues._form')

                <div class="mt-5 flex flex-wrap gap-3">
                    <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Enregistrer les modifications
                    </button>
                    <a href="{{ route('issues.show', $issue) }}" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
