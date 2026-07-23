@php
    $progress = $project->progressStats();
    $total = $progress['total'];
    $done = $progress['done'];
    $open = $progress['open'];
    $percent = $progress['percent'];
    $stack = is_array($project->stack) ? array_slice($project->stack, 0, 4) : [];
    $key = 'PRJ-'.$project->id;
@endphp

<article
    class="pm-card pm-card--{{ $project->status->tone() }}"
    data-project-id="{{ $project->id }}"
    data-status="{{ $project->status->value }}"
>
    <div class="pm-card__top">
        <div class="pm-card__identity">
            @if($project->logoUrl())
                <img src="{{ $project->logoUrl() }}" alt="" class="pm-card__logo" draggable="false">
            @else
                <span class="pm-card__logo pm-card__logo--fallback">{{ strtoupper(mb_substr($project->name, 0, 1)) }}</span>
            @endif
            <div class="min-w-0">
                <div class="pm-card__meta">
                    <span class="pm-card__key">{{ $key }}</span>
                    @if($project->category)
                        <span class="pm-card__category">{{ $project->category }}</span>
                    @endif
                </div>
                <h3 class="pm-card__title">
                    <a href="{{ route('admin.projects.edit', $project) }}" draggable="false">{{ $project->name }}</a>
                </h3>
            </div>
        </div>
        <span class="pm-status pm-status--{{ $project->status->tone() }}" data-status-label>{{ $project->status->label() }}</span>
    </div>

    @if($project->information)
        <p class="pm-card__summary">{{ \Illuminate\Support\Str::limit($project->information, 110) }}</p>
    @endif

    <div class="pm-card__progress">
        <div class="pm-card__progress-head">
            <span>{{ $progress['source'] === 'steps' ? 'Etapas' : 'Progresso' }}</span>
            <span data-progress-label>
                @if($percent === null)
                    Sem etapas
                @else
                    {{ $done }}/{{ $total }} · {{ $percent }}%
                @endif
            </span>
        </div>
        <div class="pm-card__bar" aria-hidden="true">
            <span data-progress-bar style="width: {{ $percent ?? 0 }}%"></span>
        </div>
    </div>

    @if($stack !== [])
        <div class="pm-card__tags">
            @foreach($stack as $tech)
                <span class="pm-tag">{{ $tech }}</span>
            @endforeach
        </div>
    @endif

    <div class="pm-card__flags">
        @if($project->is_public)
            <span class="pm-flag pm-flag--public">No site</span>
        @else
            <span class="pm-flag">Interno</span>
        @endif
        @if($project->repo_is_private)
            <span class="pm-flag">Repo privado</span>
        @endif
        @if($open > 0 && $progress['source'] === 'steps')
            <span class="pm-flag pm-flag--open">{{ $open }} etapa{{ $open === 1 ? '' : 's' }}</span>
        @elseif($open > 0)
            <span class="pm-flag pm-flag--open">{{ $open }} em aberto</span>
        @endif
    </div>

    <div class="pm-card__actions">
        <div class="pm-card__links">
            @if($project->website_url)
                <a href="{{ $project->website_url }}" target="_blank" rel="noopener" title="Site" draggable="false">Site</a>
            @endif
            @if($project->github_url)
                <a href="{{ $project->github_url }}" target="_blank" rel="noopener" title="GitHub" draggable="false">GitHub</a>
            @endif
            <a href="{{ route('admin.tasks.index', ['view' => 'board']) }}" title="Agenda" draggable="false">Agenda</a>
        </div>
        <div class="pm-card__ops">
            <a href="{{ route('admin.projects.edit', $project) }}" class="pm-card__btn" draggable="false">Abrir</a>
            <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" data-confirm="Remover este projeto?">
                @csrf
                @method('DELETE')
                <button type="submit" class="pm-card__btn pm-card__btn--danger">Excluir</button>
            </form>
        </div>
    </div>
</article>
