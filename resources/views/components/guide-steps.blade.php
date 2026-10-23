@props(['steps' => [], 'empty' => 'Aucune étape renseignée.'])

@if (empty($steps))
    <p class="text-sm text-slate-600">{{ $empty }}</p>
@else
    <ol class="space-y-4">
        @foreach ($steps as $step)
            <li class="flex gap-3">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">
                    {{ $loop->iteration }}
                </span>
                <div>
                    <p class="font-medium text-slate-800">{{ $step['title'] }}</p>
                    @if (! empty($step['items']))
                        <ul class="mt-1 list-inside list-disc space-y-1 text-sm text-slate-600">
                            @foreach ($step['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
