@php
    $items = collect($items ?? [])->values();
    $variant = $variant ?? 'open';
    $featuredCards = (bool) ($featuredCards ?? false);
    $cardClass = $cardClass ?? '';
    $perPage = max(2, (int) ($perPage ?? ($variant === 'featured' ? 2 : 3)));
    $bookId = $bookId ?? \Illuminate\Support\Str::slug($title ?? 'portfolio');
    $pages = $items->chunk($perPage)->values();
    $pageCount = $pages->count();
    $canFlip = $pageCount > 1;
@endphp
@if($pages->isNotEmpty())
    <article
        class="project-book project-book--{{ $variant }}{{ $canFlip ? '' : ' is-single' }}"
        data-project-book
        data-book-pages="{{ $pageCount }}"
        aria-labelledby="project-book-{{ $bookId }}-title"
    >
        <div class="project-book__spine" aria-hidden="true"></div>

        <header class="project-book__head">
            <div class="min-w-0">
                @if(! empty($kicker))
                    <p class="section-kicker">{{ $kicker }}</p>
                @endif
                <h3 id="project-book-{{ $bookId }}-title" class="font-display text-xl font-semibold text-snow sm:text-2xl">{{ $title }}</h3>
                @if(! empty($lead))
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-mist">{{ $lead }}</p>
                @endif
            </div>
            <div class="project-book__meta">
                @if(! empty($badge))
                    <span class="project-book__badge">{{ $badge }}</span>
                @endif
                @if($canFlip)
                    <p class="project-book__folio" data-book-folio aria-live="polite">1 / {{ $pageCount }}</p>
                @endif
            </div>
        </header>

        <div class="project-book__stage" data-book-stage @if($canFlip) tabindex="0" aria-label="Páginas de {{ $title }}" @endif>
            <div class="project-book__bind">
                @foreach($pages as $index => $projectsOnPage)
                    <div
                        class="project-book__page{{ $index === 0 ? ' is-current' : ($index === 1 ? ' is-next' : ' is-after') }}"
                        data-book-page
                        aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
                        @if($index !== 0) inert @endif
                    >
                        <div class="project-book__sheet">
                            <div class="project-book__spread">
                                @foreach($projectsOnPage as $project)
                                    <x-site.project-card
                                        :project="$project"
                                        :featured="$featuredCards"
                                        :class="$cardClass"
                                    />
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($canFlip)
                <div class="project-book__controls">
                    <button type="button" class="project-book__turn" data-book-prev aria-label="Página anterior">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19 8 12l7-7"/></svg>
                        <span>Anterior</span>
                    </button>
                    <button type="button" class="project-book__turn" data-book-next aria-label="Próxima página">
                        <span>Próxima</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 5 7 7-7 7"/></svg>
                    </button>
                </div>
            @endif
        </div>
    </article>
@endif
