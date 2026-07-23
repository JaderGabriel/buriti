@extends('layouts.admin')

@section('content')
<div class="pm-workspace">
    <div class="pm-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">Gestão de projetos</p>
            <h1 class="pm-workspace__title">Portfólio operacional</h1>
            <p class="pm-workspace__lead">Arraste cards entre colunas ou na vertical para ordenar, e acompanhe o % pelas etapas do projeto.</p>
        </div>
        <div class="pm-workspace__actions">
            <a href="{{ route('admin.tasks.index') }}" class="pm-btn pm-btn--ghost">
                <x-ui.icon name="task" class="h-4 w-4" />
                Agenda
            </a>
            <a href="{{ route('admin.projects.create') }}" class="pm-btn pm-btn--primary">
                <x-ui.icon name="project" class="h-4 w-4" />
                Novo projeto
            </a>
        </div>
    </div>

    <div class="pm-stats">
        <div class="pm-stat">
            <span class="pm-stat__value">{{ $stats['total'] }}</span>
            <span class="pm-stat__label">Total</span>
        </div>
        <div class="pm-stat pm-stat--active">
            <span class="pm-stat__value">{{ $stats['active'] }}</span>
            <span class="pm-stat__label">Ativos</span>
        </div>
        <div class="pm-stat pm-stat--paused">
            <span class="pm-stat__value">{{ $stats['paused'] }}</span>
            <span class="pm-stat__label">Pausados</span>
        </div>
        <div class="pm-stat pm-stat--done">
            <span class="pm-stat__value">{{ $stats['done'] }}</span>
            <span class="pm-stat__label">Concluídos</span>
        </div>
        <div class="pm-stat">
            <span class="pm-stat__value">{{ $stats['public'] }}</span>
            <span class="pm-stat__label">No site</span>
        </div>
    </div>

    <div class="pm-toolbar">
        <div class="pm-view-switch" role="tablist" aria-label="Vista de projetos">
            <a
                href="{{ route('admin.projects.index', array_filter(['view' => 'board', 'status' => $statusFilter])) }}"
                @class(['is-active' => $view === 'board'])
            >Board</a>
            <a
                href="{{ route('admin.projects.index', array_filter(['view' => 'list', 'status' => $statusFilter])) }}"
                @class(['is-active' => $view === 'list'])
            >Lista</a>
        </div>

        <div class="pm-toolbar__end">
            <div class="pm-filters">
                <a
                    href="{{ route('admin.projects.index', ['view' => $view]) }}"
                    @class(['pm-chip', 'is-active' => $statusFilter === null])
                >Todos</a>
                @foreach($statusLabels as $value => $label)
                    <a
                        href="{{ route('admin.projects.index', ['view' => $view, 'status' => $value]) }}"
                        @class(['pm-chip', 'pm-chip--'.$value, 'is-active' => $statusFilter === $value])
                    >{{ $label }}</a>
                @endforeach
            </div>

            @if($view === 'board')
                <div class="pm-density" data-pm-density>
                    <button type="button" class="pm-chip" data-pm-minimize-all title="Só visual — não altera dados">Minimizar todos</button>
                    <button type="button" class="pm-chip" data-pm-expand-all title="Só visual — não altera dados">Expandir todos</button>
                </div>
            @endif
        </div>
    </div>

    @if($view === 'board')
        <div
            class="pm-board"
            data-project-board
            data-status-url-template="{{ $statusMoveUrlTemplate }}"
        >
            @foreach($statusLabels as $status => $label)
                <section
                    class="pm-board__column pm-board__column--{{ $status }}"
                    data-status="{{ $status }}"
                >
                    <header class="pm-board__header">
                        <div>
                            <h2>{{ $label }}</h2>
                            <p>Solte aqui para mover</p>
                        </div>
                        <span class="pm-board__count" data-column-count>{{ $columns[$status]->count() }}</span>
                    </header>
                    <div class="pm-board__list" data-column-list>
                        @forelse($columns[$status] as $project)
                            @include('admin.projects.partials.card', ['project' => $project])
                        @empty
                            <p class="pm-board__empty" data-empty>Nenhum projeto neste estágio.</p>
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
                        <th>Projeto</th>
                        <th>Status</th>
                        <th>Progresso</th>
                        <th>Visibilidade</th>
                        <th>Links</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projects as $project)
                        @php $progress = $project->progressStats(); @endphp
                        <tr>
                            <td>
                                <div class="pm-table__project">
                                    @if($project->logoUrl())
                                        <img src="{{ $project->logoUrl() }}" alt="" class="pm-card__logo">
                                    @else
                                        <span class="pm-card__logo pm-card__logo--fallback">{{ strtoupper(mb_substr($project->name, 0, 1)) }}</span>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.projects.edit', $project) }}" class="pm-table__name">{{ $project->name }}</a>
                                        <p class="pm-table__sub">PRJ-{{ $project->id }}@if($project->category) · {{ $project->category }}@endif</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="pm-status pm-status--{{ $project->status->tone() }}">{{ $project->status->label() }}</span></td>
                            <td>
                                @if($progress['percent'] === null)
                                    <span class="text-mist">—</span>
                                @else
                                    <div class="pm-table__progress">
                                        <div class="pm-card__bar"><span style="width: {{ $progress['percent'] }}%"></span></div>
                                        <span>{{ $progress['percent'] }}%</span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="pm-card__flags">
                                    @if($project->is_public)
                                        <span class="pm-flag pm-flag--public">Site</span>
                                    @else
                                        <span class="pm-flag">Interno</span>
                                    @endif
                                    @if($project->repo_is_private)
                                        <span class="pm-flag">Privado</span>
                                    @endif
                                </div>
                            </td>
                            <td class="pm-card__links">
                                @if($project->website_url)
                                    <a href="{{ $project->website_url }}" target="_blank" rel="noopener">Site</a>
                                @endif
                                @if($project->github_url)
                                    <a href="{{ $project->github_url }}" target="_blank" rel="noopener">GitHub</a>
                                @endif
                            </td>
                            <td class="pm-table__ops">
                                <a href="{{ route('admin.projects.edit', $project) }}" class="pm-card__btn">Abrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="pm-board__empty">Nenhum projeto cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $projects->links() }}</div>
    @endif
</div>
@endsection
