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
        </div>
        <span class="jirau-column__count">{{ $count }}</span>
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
