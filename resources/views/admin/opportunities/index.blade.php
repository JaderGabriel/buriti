@extends('layouts.admin')

@section('content')
<div class="crm-workspace">
    <div class="crm-workspace__header">
        <div>
            <p class="crm-workspace__eyebrow">CRM comercial</p>
            <h1 class="crm-workspace__title">Pipeline de oportunidades</h1>
            <p class="crm-workspace__lead">Arraste os cards entre etapas do funil — ou abra a ficha para editar.</p>
        </div>
        <div class="crm-workspace__actions">
            <a href="{{ route('admin.contacts.index') }}" class="pm-btn pm-btn--ghost">
                <x-ui.icon name="contact" class="h-4 w-4" />
                Contatos
            </a>
            <a href="{{ route('admin.opportunities.create') }}" class="pm-btn pm-btn--primary">
                <x-ui.icon name="opportunity" class="h-4 w-4" />
                Nova oportunidade
            </a>
        </div>
    </div>

    <x-admin.crm-journey current="opportunity" class="mb-5" />

    <x-admin.crm-funnel
        :counts="$counts"
        :active="$stageFilter"
        :filter-base="route('admin.opportunities.index', ['view' => $view])"
        class="mb-5"
    />

    <div class="pm-stats mb-5">
        <div class="pm-stat">
            <span class="pm-stat__value">{{ $stats['total'] }}</span>
            <span class="pm-stat__label">Total</span>
        </div>
        <div class="pm-stat pm-stat--active">
            <span class="pm-stat__value">{{ $stats['open'] }}</span>
            <span class="pm-stat__label">Em aberto</span>
        </div>
        <div class="pm-stat pm-stat--done">
            <span class="pm-stat__value">{{ $stats['won'] }}</span>
            <span class="pm-stat__label">Contratos</span>
        </div>
        <div class="pm-stat pm-stat--paused">
            <span class="pm-stat__value">{{ $stats['lost'] }}</span>
            <span class="pm-stat__label">Perdidos</span>
        </div>
        <div class="pm-stat">
            <span class="pm-stat__value">R$ {{ number_format($stats['pipeline_value'], 0, ',', '.') }}</span>
            <span class="pm-stat__label">Pipeline</span>
        </div>
    </div>

    <div class="pm-toolbar">
        <div class="pm-view-switch" role="tablist" aria-label="Vista do pipeline">
            <a href="{{ route('admin.opportunities.index', array_filter(['view' => 'board', 'stage' => $stageFilter])) }}" @class(['is-active' => $view === 'board'])>Board</a>
            <a href="{{ route('admin.opportunities.index', array_filter(['view' => 'list', 'stage' => $stageFilter])) }}" @class(['is-active' => $view === 'list'])>Lista</a>
        </div>
        @if($stageFilter)
            <a href="{{ route('admin.opportunities.index', ['view' => $view]) }}" class="pm-chip">Limpar filtro</a>
        @endif
    </div>

    @if($view === 'board')
        <div
            class="crm-board"
            data-opportunity-board
            data-stage-url-template="{{ $stageMoveUrlTemplate }}"
        >
            @foreach($stageMeta as $meta)
                @php $items = $columns[$meta['value']] ?? collect(); @endphp
                <section
                    class="crm-board__column crm-board__column--{{ $meta['tone'] }}"
                    data-stage="{{ $meta['value'] }}"
                >
                    <header class="crm-board__header">
                        <div class="crm-board__title">
                            <span class="crm-board__icon"><x-ui.icon :name="$meta['icon']" class="h-4 w-4" /></span>
                            <div>
                                <h2>{{ $meta['label'] }}</h2>
                                <p>Solte aqui para mover</p>
                            </div>
                        </div>
                        <span class="pm-board__count" data-column-count>{{ $items->count() }}</span>
                    </header>
                    <div class="crm-board__list" data-column-list>
                        @forelse($items as $opportunity)
                            <article
                                class="crm-deal"
                                data-opportunity-id="{{ $opportunity->id }}"
                                data-stage="{{ $opportunity->stage->value }}"
                            >
                                <div class="crm-deal__top">
                                    <x-admin.crm-badge :stage="$opportunity->stage" compact data-stage-badge />
                                    @if($opportunity->value)
                                        <span class="crm-deal__value">R$ {{ number_format((float) $opportunity->value, 0, ',', '.') }}</span>
                                    @endif
                                </div>
                                <h3 class="crm-deal__title">
                                    <a href="{{ route('admin.opportunities.edit', $opportunity) }}" draggable="false">{{ $opportunity->title }}</a>
                                </h3>
                                <p class="crm-deal__contact">
                                    <a href="{{ route('admin.contacts.show', $opportunity->contact) }}" draggable="false">{{ $opportunity->contact->name }}</a>
                                    @if($opportunity->project)
                                        · {{ $opportunity->project->name }}
                                    @endif
                                </p>
                                @if($opportunity->expected_close_at)
                                    <p class="crm-deal__date">Prev. {{ $opportunity->expected_close_at->format('d/m/Y') }}</p>
                                @endif
                                <a href="{{ route('admin.opportunities.edit', $opportunity) }}" class="crm-deal__open" draggable="false">Abrir</a>
                            </article>
                        @empty
                            <p class="pm-board__empty" data-empty>Sem cards neste estágio.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @else
        <div class="pm-table-wrap">
            <table class="pm-table">
                <thead>
                    <tr>
                        <th>Oportunidade</th>
                        <th>Contato</th>
                        <th>Estágio</th>
                        <th>Valor</th>
                        <th>Previsão</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($opportunities as $opportunity)
                        <tr>
                            <td>
                                <a href="{{ route('admin.opportunities.edit', $opportunity) }}" class="pm-table__name">{{ $opportunity->title }}</a>
                                <p class="pm-table__sub">{{ $opportunity->project?->name ?? 'Sem projeto' }}</p>
                            </td>
                            <td>
                                <a href="{{ route('admin.contacts.show', $opportunity->contact) }}" class="text-brand-bright hover:underline">
                                    {{ $opportunity->contact->name }}
                                </a>
                            </td>
                            <td><x-admin.crm-badge :stage="$opportunity->stage" /></td>
                            <td class="text-mist">
                                @if($opportunity->value)
                                    R$ {{ number_format((float) $opportunity->value, 2, ',', '.') }}
                                @else — @endif
                            </td>
                            <td class="text-mist">
                                {{ $opportunity->expected_close_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="pm-table__ops">
                                <a href="{{ route('admin.opportunities.edit', $opportunity) }}" class="pm-card__btn">Abrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="pm-board__empty">Nenhuma oportunidade.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $opportunities->links() }}</div>
    @endif
</div>
@endsection
