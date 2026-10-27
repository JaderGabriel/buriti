@php
    $snapshot = $timeSnapshot ?? null;
@endphp
@if($canSeeTimeHistory ?? false)
    <section class="jirau-drawer__block">
        <h3>Histórico de horas</h3>
        @if(! $snapshot || $snapshot['total'] === 0)
            <p class="jirau-hint">Ainda sem apontamento neste cartão.</p>
        @else
            <p class="jirau-time__total">{{ $snapshot['total_label'] }} no total</p>
            <ul class="jirau-time__columns">
                @foreach($snapshot['columns'] as $column)
                    <li>
                        <strong>{{ $column['name'] }}</strong>
                        <span>{{ $column['label'] }}</span>
                        <small>
                            @foreach($column['people'] as $person)
                                {{ $person['name'] }} · {{ $person['label'] }}{{ $loop->last ? '' : ' · ' }}
                            @endforeach
                        </small>
                    </li>
                @endforeach
            </ul>
            <ul class="jirau-time__log">
                @foreach($snapshot['entries'] as $entry)
                    <li @class(['is-running' => $entry->isRunning()])>
                        <strong>{{ $entry->user?->name ?? 'Alguém' }}</strong>
                        <span>{{ $entry->column_name }} · {{ $entry->elapsedLabel() }}{{ $entry->isRunning() ? ' · em curso' : '' }}</span>
                        <time>{{ $entry->rangeLabel() }}</time>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
