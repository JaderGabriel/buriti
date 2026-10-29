@php
    $watchers = $column->watchers;
@endphp
<x-jirau.dialog name="column-{{ $column->id }}" :title="$column->name" eyebrow="Etapa">
    <form method="POST" action="{{ route('jirau.columns.update', [$jirau, $column]) }}" class="jirau-dialog__form jirau-watch__form">
        @csrf
        @method('PUT')
        <input type="hidden" name="sync_watchers" value="1">
        <input type="hidden" name="name" value="{{ $column->name }}">
        <fieldset class="jirau-watch__colors">
            <legend>Cor</legend>
            @foreach(\App\Models\JirauColumn::colorOptions() as $hex)
                <label title="{{ $hex }}">
                    <input type="radio" name="color" value="{{ $hex }}" @checked(strcasecmp((string) $column->color, $hex) === 0)>
                    <i style="background: {{ $hex }}"></i>
                </label>
            @endforeach
            <label class="jirau-watch__color-custom" title="Outra cor">
                <input type="color" data-jirau-color-picker value="{{ $column->color ?: '#2f6bff' }}">
                <input type="radio" name="color" value="{{ $column->color ?: '#2f6bff' }}" data-jirau-color-custom @checked($column->color && ! in_array(mb_strtolower((string) $column->color), array_map('strtolower', \App\Models\JirauColumn::colorOptions()), true))>
            </label>
        </fieldset>
        <label class="jirau-watch__field">
            <span>Prazo padrão</span>
            <select name="default_due_hours" class="jirau-input">
                <option value="">Sem prazo automático</option>
                @foreach(\App\Models\JirauColumn::defaultDueOptions() as $hours => $label)
                    <option value="{{ $hours }}" @selected((int) $column->default_due_hours === (int) $hours)>{{ $label }}</option>
                @endforeach
            </select>
            <small>Cartões que entram aqui sem data recebem este prazo.</small>
        </label>
        <label class="jirau-watch__field">
            <span>Faixa no quadro geral</span>
            <select name="overview_lane" class="jirau-input" @disabled($column->overviewLaneIsLocked())>
                @foreach(\App\Enums\JirauOverviewLane::options() as $value => $label)
                    <option value="{{ $value }}" @selected($column->overviewLane()->value === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <small>
                @if($column->overviewLaneIsLocked())
                    Inbox e Feito ficam fixos no quadro geral.
                @else
                    Cartões desta etapa entram nesta coluna do quadro geral, mesmo com faixas extras neste Jirau.
                @endif
            </small>
        </label>
        <p class="jirau-watch__kicker">Quem avisar</p>
        <p class="jirau-watch__help">Quem entra nesta etapa gera aviso ⚡ no Telegram.</p>
        @if($watchers->isNotEmpty())
            <ul class="jirau-watch__picked">
                @foreach($watchers as $watcher)
                    <li>
                        <span class="jirau-avatar">{{ $watcher->initials() }}</span>
                        {{ $watcher->name }}
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="jirau-watch__people">
            @foreach($assignees as $person)
                <label @class(['is-on' => $watchers->contains('id', $person->id)])>
                    <input type="checkbox" name="user_ids[]" value="{{ $person->id }}" @checked($watchers->contains('id', $person->id))>
                    <span class="jirau-avatar">{{ $person->initials() }}</span>
                    <span>
                        <strong>{{ $person->name }}</strong>
                        <small>{{ $person->jirauTeamLabel() }}</small>
                    </span>
                </label>
            @endforeach
        </div>
        <button type="submit" class="pm-btn pm-btn--primary">Salvar etapa</button>
    </form>
</x-jirau.dialog>
