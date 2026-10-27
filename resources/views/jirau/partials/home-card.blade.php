@php
    $template = $board->template;
    $open = (int) $board->open_cards_count;
    $inbox = (int) $board->inbox_cards_count;
    $draggable = (bool) ($draggable ?? false);
    $canDelete = (bool) ($canDelete ?? false);
@endphp
<article
    class="jirau-home__card {{ $board->is_active ? '' : 'is-inactive' }}"
    data-jirau-home-card
    data-board-id="{{ $board->id }}"
    style="--jirau-col: {{ $template?->color() ?? '#2f6bff' }}"
>
    <header class="jirau-home__card-head">
        <span class="jirau-home__tools">
            <span class="jirau-home__mark" aria-hidden="true">
                <x-ui.icon :name="$template?->icon() ?? 'jirau'" class="h-4 w-4" />
            </span>
            <span class="jirau-home__count" title="Cartões abertos">{{ $open }}</span>
        </span>
        <span class="jirau-home__tools">
            @if($canDelete)
                <form
                    method="POST"
                    action="{{ route('jirau.boards.destroy', $board) }}"
                    data-confirm="Remover o quadro «{{ $board->name }}»? Só quadros sem cartões podem ser apagados."
                >
                    @csrf
                    @method('DELETE')
                    <x-jirau.action tone="warn" icon="trash" icon-only type="submit" aria-label="Remover quadro" title="Remover quadro"></x-jirau.action>
                </form>
            @endif
            @if($draggable)
                <button type="button" class="jirau-home__grip" data-jirau-home-handle aria-label="Reordenar {{ $board->name }}" title="Arrastar para reordenar">
                    <x-ui.icon name="grip" class="h-4 w-4" />
                </button>
            @endif
        </span>
    </header>
    <p class="jirau-home__kicker">{{ $board->project?->name ?? 'Projeto' }}</p>
    <h2>{{ $board->name }}</h2>
    <div class="jirau-home__chips">
        @if($board->company)
            <span class="jirau-chip" title="Empresa / contrato">{{ $board->company->displayName() }}</span>
        @endif
        @if($board->contract_name)
            <span class="jirau-chip" title="Contrato">{{ $board->contract_name }}</span>
        @endif
        @if($template)
            <span class="jirau-chip">{{ $template->label() }}</span>
        @endif
        @unless($board->is_active)
            <span class="jirau-chip jirau-chip--warn">Inativo</span>
        @endunless
    </div>
    <p class="jirau-home__meta">
        @if($inbox > 0)
            {{ $inbox }} na triagem
        @else
            Sem pedidos na triagem
        @endif
    </p>
    <div class="jirau-home__foot">
        <a href="{{ route('jirau.show', $board) }}" class="jirau-home__open">
            <x-ui.icon name="jirau" class="h-4 w-4" />
            <span>{{ $board->is_active ? 'Abrir quadro' : 'Ver quadro' }}</span>
        </a>
        @include('jirau.partials.project-mark', [
            'project' => $board->project,
            'markClass' => 'jirau-home__brand',
        ])
    </div>
</article>
