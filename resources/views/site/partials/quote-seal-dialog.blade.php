@php
    $quote = $quote ?? null;
    $dialogId = 'quote-seal-'.($quote?->id ?? '0');
    $stars = (int) ($quote?->stars() ?? 0);
@endphp
@if($quote)
    <div
        id="{{ $dialogId }}"
        class="quote-dialog quote-dialog--seal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $dialogId }}-title"
        hidden
    >
        <button type="button" class="quote-dialog__backdrop" data-dialog-close aria-label="Fechar autenticidade"></button>
        <div class="quote-dialog__panel" role="document">
            <div class="quote-dialog__head">
                <div class="min-w-0">
                    <p class="section-kicker">Autenticidade</p>
                    <h3 id="{{ $dialogId }}-title" class="font-display text-xl font-bold text-snow sm:text-2xl">Selo autêntico</h3>
                </div>
                <button
                    type="button"
                    class="quote-dialog__close"
                    data-dialog-close
                    aria-label="Fechar autenticidade"
                >
                    <span aria-hidden="true">✕</span>
                </button>
            </div>
            <div class="quote-dialog__body quote-seal">
                <p class="quote-seal__ok">
                    <x-ui.icon name="verified" class="h-4 w-4" />
                    Original selado e íntegro
                </p>
                <div class="quote-card__stars" aria-label="{{ $stars }} estrelas">
                    @for($i = 1; $i <= 5; $i++)
                        <span @class(['is-on' => $i <= $stars])>★</span>
                    @endfor
                </div>
                <p class="quote-card__body">{{ $quote->publishedQuote() }}</p>
                <p class="quote-seal__who">
                    <strong>{{ $quote->displayName() }}</strong>
                    @if($quote->project)
                        <span>{{ $quote->project->name }}</span>
                    @endif
                </p>
                @if($quote->wasMediated())
                    <p class="quote-seal__note">A versão exibida foi mediada. O original do cliente permanece selado e íntegro.</p>
                @else
                    <p class="quote-seal__note">O texto publicado é igual ao original do cliente.</p>
                @endif
                @if($quote->verification_code)
                    <p class="quote-seal__code">{{ $quote->verification_code }}</p>
                @endif
                <p class="quote-seal__fp">origem {{ $quote->sourceFingerprint() ?: '—' }} · resultado {{ $quote->resultFingerprint() ?: '—' }}</p>
            </div>
        </div>
    </div>
@endif
