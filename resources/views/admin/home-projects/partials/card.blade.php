<article
    class="home-board__card"
    data-home-project-id="{{ $project->id }}"
>
    <div class="home-board__card-top">
        @if($project->logoUrl())
            <img src="{{ $project->logoUrl() }}" alt="" class="home-board__logo" draggable="false">
        @else
            <span class="home-board__logo home-board__logo--fallback">{{ strtoupper(mb_substr($project->name, 0, 1)) }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <p class="home-board__name">{{ $project->name }}</p>
            <p class="home-board__meta">
                {{ $project->category ?: 'Sem categoria' }}
                @if($project->repo_is_private) · repo privado @endif
            </p>
        </div>
        <button
            type="button"
            class="home-board__star {{ ($lane ?? '') === 'featured' ? 'is-on' : '' }}"
            data-home-star
            title="{{ ($lane ?? '') === 'featured' ? 'Tirar estrela' : 'Destacar com estrela' }}"
            aria-pressed="{{ ($lane ?? '') === 'featured' ? 'true' : 'false' }}"
        >
            <x-ui.icon name="star" class="h-4 w-4" />
        </button>
    </div>
    <a href="{{ route('admin.projects.edit', $project) }}" class="home-board__open" draggable="false">Abrir ficha</a>
</article>
