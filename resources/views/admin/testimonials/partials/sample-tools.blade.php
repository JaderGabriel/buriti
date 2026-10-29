@php $sampleCount = (int) ($sampleCount ?? 0); @endphp
<article class="testimonial-samples">
    <div class="testimonial-samples__copy">
        <p class="testimonial-samples__kicker">Amostras visuais</p>
        <h3>Testes de depoimento</h3>
        <p>Gera 5 comentários aprovados com a flag <code>is_sample</code> para conferir a faixa pública e o modal nos projetos. Não são clientes reais.</p>
        <p class="testimonial-samples__count">{{ $sampleCount }} amostra{{ $sampleCount === 1 ? '' : 's' }} na base</p>
    </div>
    <div class="testimonial-samples__actions">
        <form method="POST" action="{{ route('admin.testimonials.samples.store') }}" data-confirm="Isto substitui as amostras visuais atuais por 5 depoimentos de teste.">
            @csrf
            <button type="submit" class="pm-btn pm-btn--primary">
                <x-ui.icon name="quote" class="h-4 w-4" />
                Gerar 5 testes
            </button>
        </form>
        <form method="POST" action="{{ route('admin.testimonials.samples.destroy') }}" data-confirm="Apagar só os depoimentos com a flag is_sample?">
            @csrf
            @method('DELETE')
            <button type="submit" class="pm-btn pm-btn--ghost" @disabled($sampleCount === 0)>
                <x-ui.icon name="trash" class="h-4 w-4" />
                Apagar testes
            </button>
        </form>
    </div>
</article>
