@props([
    'quote',
    'showProject' => false,
])

<blockquote {{ $attributes->class(['quote-card']) }}>
    <div class="quote-card__stars" aria-label="{{ (int) $quote->stars() }} estrelas">
        @for($i = 1; $i <= 5; $i++)
            <span @class(['is-on' => $i <= (int) $quote->stars()])>★</span>
        @endfor
    </div>
    <p class="quote-card__body">{{ $quote->publishedQuote() }}</p>
    <footer class="quote-card__foot">
        <cite>{{ $quote->displayName() }}</cite>
        @if($showProject && $quote->project)
            <span class="quote-card__project">
                {{ $quote->project->name }}
                @if($quote->verification_code)
                    <button
                        type="button"
                        class="quote-card__seal"
                        data-dialog-open="quote-seal-{{ $quote->id }}"
                        aria-label="Ver autenticidade do depoimento"
                        title="Ver autenticidade"
                    >
                        <x-ui.icon name="verified" class="h-4 w-4" />
                    </button>
                @endif
            </span>
        @elseif($quote->verification_code)
            <button
                type="button"
                class="quote-card__seal"
                data-dialog-open="quote-seal-{{ $quote->id }}"
                aria-label="Ver autenticidade do depoimento"
                title="Ver autenticidade"
            >
                <x-ui.icon name="verified" class="h-4 w-4" />
            </button>
        @endif
    </footer>
</blockquote>
