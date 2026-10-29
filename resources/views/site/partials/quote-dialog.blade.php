@php
    $quotes = collect($quotes ?? []);
    $project = $project ?? $quotes->first()?->project;
    $dialogId = 'quote-project-'.($project?->id ?? '0');
@endphp
@if($project && $quotes->isNotEmpty())
    <div
        id="{{ $dialogId }}"
        class="quote-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $dialogId }}-title"
        hidden
    >
        <button type="button" class="quote-dialog__backdrop" data-dialog-close aria-label="Fechar depoimentos"></button>
        <div class="quote-dialog__panel" role="document">
            <div class="quote-dialog__head">
                <div class="min-w-0">
                    <p class="section-kicker">Depoimentos</p>
                    <h3 id="{{ $dialogId }}-title" class="font-display text-xl font-bold text-snow sm:text-2xl">{{ $project->name }}</h3>
                    <p class="mt-1 text-sm text-mist">{{ $quotes->count() }} depoimento{{ $quotes->count() === 1 ? '' : 's' }} aprovado{{ $quotes->count() === 1 ? '' : 's' }}</p>
                </div>
                <button
                    type="button"
                    class="quote-dialog__close"
                    data-dialog-close
                    aria-label="Fechar depoimentos"
                >
                    <span aria-hidden="true">✕</span>
                </button>
            </div>
            <div class="quote-dialog__body">
                @foreach($quotes as $quote)
                    <x-site.quote-card :quote="$quote" />
                @endforeach
            </div>
        </div>
    </div>
@endif
