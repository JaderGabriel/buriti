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
            <span>{{ $quote->project->name }}</span>
        @endif
        @if($quote->verification_code)
            <a href="{{ route('testimonials.seal', $quote->verification_code) }}" class="quote-card__seal">Selo</a>
        @endif
    </footer>
</blockquote>
