@php
    $items = collect($items ?? []);
    $variant = $variant ?? 'open';
    $featuredCards = (bool) ($featuredCards ?? false);
    $cardClass = $cardClass ?? '';
    $bookId = $bookId ?? \Illuminate\Support\Str::slug($title ?? 'portfolio');
    $chapters = $items
        ->groupBy(fn ($project) => filled($project->category) ? $project->category : 'Outros');
    $pages = $chapters->flatten(1)->values();
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

        <div class="project-book__body">
            @if($canFlip)
                <nav class="project-book__toc" aria-label="Tipos em {{ $title }}">
                    @php $tocIndex = 0; @endphp
                    @foreach($chapters as $chapter => $chapterItems)
                        <p class="project-book__chapter">{{ $chapter }}</p>
                        <ol class="project-book__index">
                            @foreach($chapterItems as $project)
                                <li>
                                    <button
                                        type="button"
                                        class="project-book__toc-btn{{ $tocIndex === 0 ? ' is-active' : '' }}"
                                        data-book-goto="{{ $tocIndex }}"
                                        @if($tocIndex === 0) aria-current="page" @endif
                                    >
                                        <span>{{ $project->name }}</span>
                                        <span class="project-book__toc-leaf" aria-hidden="true">{{ $tocIndex + 1 }}</span>
                                    </button>
                                </li>
                                @php $tocIndex++; @endphp
                            @endforeach
                        </ol>
                    @endforeach
                </nav>
            @endif

            <div class="project-book__stage" data-book-stage @if($canFlip) tabindex="0" aria-label="Páginas de {{ $title }}" @endif>
                <div class="project-book__bind">
                    @foreach($pages as $index => $project)
                        <div
                            class="project-book__page{{ $index === 0 ? ' is-current' : ($index === 1 ? ' is-next' : ' is-after') }}"
                            data-book-page
                            data-category="{{ $project->category }}"
                            aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
                            @if($index !== 0) inert @endif
                        >
                            <div class="project-book__sheet">
                                <x-site.project-card
                                    :project="$project"
                                    :featured="$featuredCards"
                                    :class="$cardClass"
                                />
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
        </div>
    </article>
@endif
