<section id="servicos" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="section-kicker">Capacidades</p>
                <h2 class="section-title">Serviços para venda de TI com evidência</h2>
                <p class="mt-4 text-sm text-mist sm:text-base">Oferta completa: da consultoria ao BI, com engenharia e suporte para sustentar o contrato.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-bright hover:underline">
                <x-ui.icon name="admin" class="h-4 w-4" />
                @auth Abrir painel admin @else Entrar no painel admin @endauth
            </a>
        </div>

        <div class="mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($services as $service)
                <article class="group border-t border-line pt-6">
                    <div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-full border border-line text-brand-bright transition group-hover:border-brand-bright/50 group-hover:bg-brand/10">
                        <x-ui.icon :name="$service['icon'] ?? 'code'" class="h-5 w-5" />
                    </div>
                    <h3 class="font-display text-xl font-semibold text-snow group-hover:text-brand-bright">{{ $service['title'] }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-mist">{{ $service['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
