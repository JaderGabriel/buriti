<section id="metodo" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="section-kicker">Método comercial</p>
            <h2 class="section-title">Do contato à operação — fluxo de entrega</h2>
            <p class="mt-4 text-sm text-mist sm:text-base">
                Um caminho transparente, no estilo das consultoras de tecnologia: cada etapa com objetivo, dono e resultado mensurável.
            </p>
        </div>

        <div class="method-flow mt-12">
            @foreach($method as $index => $step)
                <div class="method-step">
                    <div class="method-node">
                        <span class="method-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="method-icon">
                            <x-ui.icon :name="$step['icon']" class="h-6 w-6" />
                        </span>
                    </div>
                    @if(! $loop->last)
                        <div class="method-connector" aria-hidden="true"></div>
                    @endif
                    <h3 class="mt-4 font-display text-lg font-semibold text-snow">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-mist">{{ $step['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
