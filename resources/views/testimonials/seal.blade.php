@extends('layouts.testimonial')

@section('title', 'Selo de depoimento — '.$product['name'])

@section('card')
<p class="testimonial-page__eyebrow">
    <x-ui.icon name="diagnose" class="h-3.5 w-3.5" />
    Autenticidade
</p>
<h1 class="testimonial-page__title">Verificar selo</h1>
<p class="testimonial-page__lead">Confirme se um depoimento aprovado ainda corresponde ao original selado pelo cliente. Não é uma galeria pública.</p>

<form method="GET" action="{{ route('testimonials.seal') }}" class="mt-6 flex gap-2">
    <input type="text" name="code" value="{{ $code }}" placeholder="BURI-XXXX-XXXX" class="login-input mt-0 min-w-0 flex-1 font-mono uppercase" autocomplete="off">
    <button type="submit" class="login-submit w-auto shrink-0 px-4">Verificar</button>
</form>

@if($code !== '')
    @if($testimonial && $authenticity && $authenticity['ok'])
        <div class="testimonial-seal-result">
            <p class="testimonial-seal-result__ok">
                <x-ui.icon name="qualified" class="h-4 w-4" />
                Selo autêntico
            </p>
            <p class="testimonial-stars" aria-label="{{ $testimonial->stars() }} estrelas">
                @for($i = 1; $i <= 5; $i++)
                    <span @class(['is-on' => $i <= (int) $testimonial->stars()])>★</span>
                @endfor
            </p>
            <p class="text-sm text-snow">{{ $testimonial->displayName() }}</p>
            @if($testimonial->project)
                <p class="testimonial-page__chip">
                    <x-ui.icon name="project" class="h-3.5 w-3.5" />
                    {{ $testimonial->project->name }}
                </p>
            @endif
            @if($testimonial->wasMediated())
                <p class="mt-3 text-sm text-amber-200">A versão exibida foi mediada. O original do cliente permanece selado e íntegro.</p>
            @else
                <p class="mt-3 text-sm text-mist">O texto publicado é igual ao original do cliente.</p>
            @endif
            <p class="mt-3 font-mono text-[11px] text-mist">origem {{ $testimonial->sourceFingerprint() }} · resultado {{ $testimonial->resultFingerprint() }}</p>
        </div>
    @else
        <p class="mt-6 inline-flex items-start gap-2 text-sm text-red-300">
            <x-ui.icon name="lost" class="mt-0.5 h-4 w-4 shrink-0" />
            Não há um depoimento aprovado com este código, ou o selo não confere.
        </p>
    @endif
@endif
@endsection
