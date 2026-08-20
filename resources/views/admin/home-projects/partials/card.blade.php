@php
    $key = 'PRJ-'.$project->id;
    $lane = $lane ?? '';
    $featured = $lane === 'featured';
@endphp

<article
    class="pm-card home-board__card"
    data-home-project-id="{{ $project->id }}"
    data-home-card
    data-repo-private="{{ $project->repo_is_private ? '1' : '0' }}"
    data-category="{{ $project->category ?: 'Sem categoria' }}"
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
        <div class="pm-card__top-side">
            <button
                type="button"
                class="pm-card__minimize"
                data-home-minimize
                aria-expanded="true"
                title="Minimizar card"
                aria-label="Minimizar {{ $project->name }}"
            >
                <span class="pm-card__minimize-icon" aria-hidden="true"></span>
            </button>
            <button
                type="button"
                class="home-board__star {{ $featured ? 'is-on' : '' }}"
                data-home-star
                title="{{ $featured ? 'Tirar estrela' : 'Destacar com estrela' }}"
                aria-pressed="{{ $featured ? 'true' : 'false' }}"
            >
                <x-ui.icon name="star" class="h-4 w-4" />
            </button>
        </div>
    </div>

    <div class="pm-card__compact" aria-hidden="true">
        <span class="pm-card__compact-progress" data-home-compact-meta>
            {{ $project->category ?: 'Sem categoria' }}
            @if($project->repo_is_private) · repo privado @endif
        </span>
        <span class="pm-flag pm-flag--public{{ $featured ? '' : ' hidden' }}" data-home-compact-featured @unless($featured) hidden @endunless>Destaque</span>
    </div>

    <div class="pm-card__body" data-home-card-body>
        @if($project->information)
            <p class="pm-card__summary">{{ \Illuminate\Support\Str::limit($project->information, 110) }}</p>
        @endif

        <div class="pm-card__flags">
            <span class="pm-flag {{ $project->is_public ? 'pm-flag--public' : '' }}" data-home-public-flag>
                {{ $project->is_public ? 'No site' : 'Interno' }}
            </span>
            <span class="pm-flag{{ $project->repo_is_private ? '' : ' hidden' }}" data-home-repo-flag @if(! $project->repo_is_private) hidden @endif>
                Repo privado
            </span>
            @if($featured)
                <span class="pm-flag pm-flag--public" data-home-featured-flag>Destaque</span>
            @else
                <span class="pm-flag pm-flag--public hidden" data-home-featured-flag hidden>Destaque</span>
            @endif
        </div>

        <p class="home-board__meta" data-home-card-meta>
            {{ $project->category ?: 'Sem categoria' }}
            @if($project->repo_is_private) · repo privado @endif
        </p>

        <div class="pm-card__actions">
            <div class="pm-card__ops">
                <a href="{{ route('admin.projects.edit', $project) }}" class="pm-card__btn" draggable="false">Abrir</a>
            </div>
        </div>
    </div>
</article>
