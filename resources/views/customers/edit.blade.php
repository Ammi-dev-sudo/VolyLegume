@extends('layouts.app')

@section('title', 'Modifier un client')

@section('content')
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-4 text-xl font-semibold text-slate-800">Modifier {{ $customer->name }}</h1>
        <x-card>
            @include('customers._form')
        </x-card>
    </div>
@endsection
