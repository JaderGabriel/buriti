@if(($homeTestimonials ?? collect())->isNotEmpty())
    <section id="depoimentos" class="section-shell quote-strip" aria-labelledby="depoimentos-title">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="quote-strip__head">
                <h2 id="depoimentos-title" class="section-title">Como foi trabalhar conosco</h2>
            </div>

            <div class="quote-strip__track">
                @foreach($homeTestimonials as $quote)
                    <x-site.quote-card :quote="$quote" show-project class="quote-strip__card" />
                @endforeach
            </div>
        </div>
    </section>
@endif
