@extends('layouts.app')

@section('title', 'Depoimento enviado — '.$product['name'])

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('body')
<div class="login-shell" data-login-page>
    <div class="login-atmosphere" aria-hidden="true"></div>
    <div class="login-frame">
        <section class="login-panel mx-auto max-w-lg">
            <div class="login-panel-inner">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-bright">Obrigado</p>
                <h1 class="mt-2 font-display text-2xl font-bold text-snow">Depoimento selado</h1>
                <p class="mt-3 text-sm leading-relaxed text-mist">
                    O texto que enviou ficou registado com um selo criptográfico. A equipa pode mediar uma versão para publicação futura, mas o original não se altera.
                </p>
                @if($testimonial->verification_code)
                    <p class="mt-4 font-mono text-sm text-brand-bright">{{ $testimonial->verification_code }}</p>
                    <p class="mt-1 text-xs text-mist">Guarde este código. Serve para confirmar a autenticidade quando o depoimento for usado.</p>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
