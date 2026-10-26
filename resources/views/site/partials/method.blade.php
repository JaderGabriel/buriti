<section id="metodo" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="section-kicker">Como trabalhamos</p>
            <h2 class="section-title">Do primeiro contato à operação no dia a dia</h2>
            <p class="section-lead">
                Cada etapa tem objetivo, responsável e entrega visível — para acompanhar o contrato com clareza.
            </p>
        </div>

        <ol class="method-flow mt-10">
            @foreach($method as $index => $step)
                @php $stepNumber = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); @endphp
                <li class="method-step">
                    <div class="method-rail" aria-hidden="true">
                        <span class="method-icon">
                            <x-ui.icon :name="$step['icon']" class="h-6 w-6" />
                        </span>
                        @unless($loop->last)
                            <span class="method-connector"></span>
                        @endunless
                    </div>
                    <div class="method-copy">
                        <span class="method-index">{{ $stepNumber }}</span>
                        <h3 class="method-title">{{ $step['title'] }}</h3>
                        <p class="method-description">{{ $step['description'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>
