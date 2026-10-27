@php
    $count = $column->cards->count();
    $over = $column->wip_limit !== null && $count > $column->wip_limit;
@endphp
<section
    class="jirau-column {{ $column->is_inbox ? 'is-inbox' : '' }} {{ $over ? 'is-over-wip' : '' }}"
    data-jirau-column
    data-column-id="{{ $column->id }}"
    style="--jirau-col: {{ $column->color ?? '#2f6bff' }}"
>
    <header class="jirau-column__head">
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
            </p>
            @if($column->watchers->isNotEmpty())
                <div class="jirau-watch__faces" title="Avisos prioritários nesta faixa">
                    @foreach($column->watchers as $watcher)
                        <span class="jirau-avatar" title="{{ $watcher->name }}">{{ $watcher->initials() }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="jirau-column__tools">
            @if($isAdmin)
                <details class="jirau-watch">
                    <summary>Avisar</summary>
                    <form method="POST" action="{{ route('jirau.columns.watchers', [$jirau, $column]) }}" class="jirau-watch__form">
                        @csrf
                        @method('PUT')
                        <p>Quem entra nesta faixa gera aviso ⚡ no Telegram.</p>
                        @foreach($assignees as $person)
                            <label>
                                <input type="checkbox" name="user_ids[]" value="{{ $person->id }}" @checked($column->watchers->contains($person->id))>
                                {{ $person->name }}
                                <small>{{ $person->jirauTeamLabel() }}</small>
                            </label>
                        @endforeach
                        <button type="submit" class="pm-btn pm-btn--primary">Guardar avisos</button>
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
