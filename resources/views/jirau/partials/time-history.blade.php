@php
    $snapshot = $timeSnapshot ?? null;
@endphp
@if($canSeeTimeHistory ?? false)
    <section class="jirau-drawer__block">
        <h3>Histórico de horas</h3>
        @if(! $snapshot || $snapshot['total'] === 0)
            <p class="jirau-hint">Ainda sem apontamento neste cartão.</p>
        @else
            <div class="jirau-time-table__wrap">
                <table class="jirau-time-table">
                    <thead>
                        <tr>
                            <th>Quem</th>
                            <th>Início</th>
                            <th>Encerrado</th>
                            <th>Tempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($snapshot['columns'] as $column)
                            <tr class="jirau-time-table__etapa" style="--jirau-col: {{ $column['color'] }}">
                                <th colspan="4">{{ $column['name'] }}</th>
                            </tr>
                            @foreach($column['rows'] as $row)
                                <tr @class(['jirau-time-table__row', 'is-running' => $row['running']])>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['started'] }}</td>
                                    <td>{{ $row['ended'] }}</td>
                                    <td>{{ $row['label'] }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="jirau-time-table__total">
                            <th colspan="3">Total</th>
                            <td>{{ $snapshot['total_label'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>
@endif
