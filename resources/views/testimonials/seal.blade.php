@extends('layouts.app')

@section('title', 'Selo de depoimento — '.$product['name'])

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('body')
<div class="login-shell" data-login-page>
    <div class="login-atmosphere" aria-hidden="true"></div>
    <div class="login-frame">
        <section class="login-panel mx-auto max-w-lg">
            <div class="login-panel-inner">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-bright">Autenticidade</p>
                <h1 class="mt-2 font-display text-2xl font-bold text-snow">Verificar selo</h1>
                <p class="mt-2 text-sm text-mist">Confirma se um depoimento aprovado ainda corresponde ao original selado pelo cliente. Não é uma galeria pública.</p>

                <form method="GET" action="{{ route('testimonials.seal') }}" class="mt-5 flex gap-2">
                    <input type="text" name="code" value="{{ $code }}" placeholder="BURI-XXXX-XXXX" class="min-w-0 flex-1 rounded-sm border border-line bg-ink/40 px-3 py-2 font-mono text-sm uppercase text-snow">
                    <button type="submit" class="rounded-full bg-brand px-4 text-sm font-semibold text-white">Verificar</button>
                </form>

                @if($code !== '')
                    @if($testimonial && $authenticity && $authenticity['ok'])
                        <div class="mt-6 space-y-2 text-sm">
                            <p class="font-semibold text-emerald-300">Selo autêntico</p>
                            <p>{{ $testimonial->displayName() }} · {{ $testimonial->stars() }} estrela{{ $testimonial->stars() === 1 ? '' : 's' }}</p>
                            @if($testimonial->project)
                                <p class="text-mist">Projeto: {{ $testimonial->project->name }}</p>
                            @endif
                            @if($testimonial->wasMediated())
                                <p class="text-amber-200">A versão mostrada foi mediada. O original do cliente permanece selado e íntegro.</p>
                            @else
                                <p class="text-mist">O texto publicado coincide com o original do cliente.</p>
                            @endif
                            <p class="font-mono text-[11px] text-mist">origem {{ $testimonial->sourceFingerprint() }} · resultado {{ $testimonial->resultFingerprint() }}</p>
                        </div>
                    @else
                        <p class="mt-6 text-sm text-red-300">Não há um depoimento aprovado com este código, ou o selo não confere.</p>
                    @endif
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
