@extends('layouts.app')

@section('title', 'Signaler un problème')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-4">
            <a href="{{ route('issues.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">&larr; Problèmes agricoles</a>
            <h1 class="mt-2 text-xl font-semibold text-slate-800">Signaler un problème agricole</h1>
            <p class="text-sm text-slate-500">Le constat reste une observation humaine : aucun diagnostic n’est présenté comme une certitude.</p>
        </div>

        <x-card>
            <form method="POST" action="{{ route('issues.store') }}" enctype="multipart/form-data">
                @csrf
                @include('issues._form')

                <div class="mt-5 flex gap-3">
                    <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
                        Enregistrer le problème
                    </button>
                    <a href="{{ route('issues.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Annuler</a>
                </div>
            </form>
        </x-card>
    </div>
@endsection
