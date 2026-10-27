@php
    $canTrackTime = $canTrackTime ?? false;
    $tracker = $tracker ?? auth()->user();
    $tracking = $canTrackTime && $card->isTrackedBy($tracker);
    $mine = $tracking ? $card->runningEntryFor($tracker) : null;
    $compact = (bool) ($compact ?? false);
@endphp
@if($canTrackTime)
    <form method="POST" action="{{ route('jirau.cards.time.toggle', [$jirau, $card]) }}" class="{{ $compact ? 'jirau-card__track' : 'jirau-time__toggle' }}">
        @csrf
        <x-jirau.action
            :tone="$tracking ? 'warn' : 'board'"
            :icon="$tracking ? 'pause' : 'play'"
            type="submit"
        >
            @if($tracking)
                Pausa{{ $mine ? ' · '.$mine->elapsedLabel() : '' }}
            @else
                Fazer
            @endif
        </x-jirau.action>
    </form>
@endif
