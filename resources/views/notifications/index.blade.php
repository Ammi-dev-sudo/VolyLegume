@extends('layouts.app')

@section('title', 'Alertes')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Alertes</h1>
            <p class="text-sm text-slate-500">Notifications de l’exploitation active (stocks critiques, etc.).</p>
        </div>
        <form method="POST" action="{{ route('notifications.readAll') }}">
            @csrf
            <button type="submit" class="rounded border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                Tout marquer comme lu
            </button>
        </form>
    </div>

    <x-card>
        @if ($notifications->isEmpty())
            <p class="text-sm text-slate-600">Aucune alerte.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($notifications as $notification)
                    @php
                        $data = $notification->data;
                        $farmName = $data['farm_name'] ?? 'Exploitation';
                    @endphp
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3 {{ $notification->read_at ? 'opacity-70' : '' }}">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">
                                {{ $data['message'] ?? 'Notification' }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $farmName }} · {{ $notification->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            @if (! empty($data['input_id']))
                                <a href="{{ route('inputs.show', $data['input_id']) }}" class="text-emerald-700 hover:underline">
                                    Voir l’intrant
                                </a>
                            @endif
                            @if ($notification->read_at)
                                <span class="text-slate-400">Lu</span>
                            @else
                                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="text-emerald-700 hover:underline">Marquer lu</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $notifications->links() }}</div>
        @endif
    </x-card>
@endsection
