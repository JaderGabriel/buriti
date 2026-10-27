@php
    $count = $column->cards->count();
    $over = $column->wip_limit !== null && $count > $column->wip_limit;
    $watchers = $column->watchers;
    $dueLabel = $column->defaultDueLabel();
    $columnRenameOpen = (string) old('_column_rename') === (string) $column->id;
    $columnMeta = collect([
        $column->wip_limit ? 'WIP '.$column->wip_limit : null,
        $column->is_inbox ? 'triagem' : null,
        $dueLabel,
    ])->filter()->implode(' · ');
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
                <div class="jirau-column__name">
                    <span class="jirau-column__count" title="{{ $count }} cartão{{ $count === 1 ? '' : 's' }}">{{ $count }}</span>
                    @if($isAdmin)
                        <h2
                            data-jirau-rename="column-{{ $column->id }}"
                            title="Clique duas vezes para alterar o nome"
                            @if($columnRenameOpen) hidden @endif
                        >{{ $column->name }}</h2>
                        <form
                            method="POST"
                            action="{{ route('jirau.columns.update', [$jirau, $column]) }}"
                            class="jirau-rename"
                            data-jirau-rename-form="column-{{ $column->id }}"
                            @unless($columnRenameOpen) hidden @endunless
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_column_rename" value="{{ $column->id }}">
                            <input
                                type="text"
                                name="name"
                                value="{{ old('_column_rename') == $column->id ? old('name', $column->name) : $column->name }}"
                                required
                                maxlength="40"
                                class="jirau-input jirau-rename__input"
                                aria-label="Nome da faixa"
                            >
                        </form>
                    @else
                        <h2>{{ $column->name }}</h2>
                    @endif
                </div>
                @if($columnMeta !== '')
                    <p>{{ $columnMeta }}</p>
                @endif
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
                <x-jirau.action
                    tone="column"
                    icon="settings"
                    icon-only
                    data-jirau-toggle="column-{{ $column->id }}"
                    aria-label="Configurar etapa {{ $column->name }}"
                    title="Configurar etapa"
                ></x-jirau.action>
            @endif
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
