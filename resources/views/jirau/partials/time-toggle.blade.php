@php
    $canTrackTime = $canTrackTime ?? false;
    $tracker = $tracker ?? auth()->user();
    $tracking = $canTrackTime && $card->isTrackedBy($tracker);
    $mine = $tracking ? $card->runningEntryFor($tracker) : null;
    $lastClosed = $card->latestClosedTimeEntry;
    $compact = (bool) ($compact ?? false);
@endphp
@if($canTrackTime)
    <form method="POST" action="{{ route('jirau.cards.time.toggle', [$jirau, $card]) }}" class="{{ $compact ? 'jirau-card__track' : 'jirau-time__toggle' }}" data-jirau-track="{{ $card->id }}">
        @csrf
        <x-jirau.action
            :tone="$tracking ? 'warn' : 'board'"
            :icon="$tracking ? 'pause' : 'play'"
            type="submit"
        >
            {{ $tracking ? 'Encerrar' : 'Fazer' }}
        </x-jirau.action>
        @if($tracking && $mine)
            <p class="jirau-time__stamp">Início {{ $mine->startedLabel() }}</p>
        @elseif($lastClosed)
            <p class="jirau-time__stamp">Última alteração {{ $lastClosed->endedLabel() }}</p>
        @endif
    </form>
@endif
