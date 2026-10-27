@php
    $count = $column->cards->count();
    $over = $column->wip_limit !== null && $count > $column->wip_limit;
    $watchers = $column->watchers;
    $dueLabel = $column->defaultDueLabel();
@endphp
<section
    class="jirau-column {{ $column->is_inbox ? 'is-inbox' : '' }} {{ $over ? 'is-over-wip' : '' }}"
    data-jirau-column
    data-column-id="{{ $column->id }}"
    style="--jirau-col: {{ $column->color ?? '#2f6bff' }}"
>
    <header class="jirau-column__head">
        <div class="jirau-column__title">
            @if($isAdmin)
                <button type="button" class="jirau-column__grip" data-jirau-column-handle aria-label="Reordenar faixa {{ $column->name }}" title="Arrastar para reordenar">
                    <span></span><span></span><span></span>
                </button>
            @endif
            <div>
                <h2>{{ $column->name }}</h2>
                <p>
                    {{ $count }}
                    @if($column->wip_limit)
                        / {{ $column->wip_limit }} WIP
                    @endif
                    @if($column->is_inbox)
                        · triagem
                    @endif
                    @if($dueLabel)
                        · {{ $dueLabel }}
                    @endif
                </p>
            </div>
        </div>
        <div class="jirau-column__tools">
            @if($watchers->isNotEmpty())
                <div class="jirau-watch__faces" title="Avisos prioritários nesta faixa">
                    @foreach($watchers as $watcher)
                        <span class="jirau-avatar" title="{{ $watcher->name }} · {{ $watcher->jirauTeamLabel() }}">{{ $watcher->initials() }}</span>
                    @endforeach
                </div>
            @endif
            @if($isAdmin)
                <details class="jirau-watch">
                    <summary class="jirau-watch__btn">
                        <x-ui.icon name="users" class="h-3.5 w-3.5" />
                        <span>Avisar</span>
                        @if($watchers->isNotEmpty())
                            <em>{{ $watchers->count() }}</em>
                        @endif
                    </summary>
                    <form method="POST" action="{{ route('jirau.columns.update', [$jirau, $column]) }}" class="jirau-watch__form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="sync_watchers" value="1">
                        <p class="jirau-watch__kicker">Faixa</p>
                        <label class="jirau-watch__field">
                            <span>Nome</span>
                            <input type="text" name="name" value="{{ $column->name }}" required maxlength="40" class="jirau-input">
                        </label>
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
                        <p class="jirau-watch__kicker">Quem avisar</p>
                        <p class="jirau-watch__help">Quem entra nesta faixa gera aviso ⚡ no Telegram.</p>
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
                        <button type="submit" class="pm-btn pm-btn--primary">Guardar faixa</button>
                    </form>
                </details>
            @endif
            <span class="jirau-column__count">{{ $count }}</span>
        </div>
    </header>

    <div class="jirau-column__list" data-jirau-list>
        @forelse($column->cards as $card)
            @include('jirau.partials.card', ['card' => $card, 'jirau' => $jirau, 'canDrag' => $isAdmin])
        @empty
            <p class="jirau-column__empty">
                {{ $column->is_inbox ? 'Nenhuma demanda na triagem.' : 'Vazio' }}
            </p>
        @endforelse
    </div>
</section>
