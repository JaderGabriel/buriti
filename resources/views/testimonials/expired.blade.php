@extends('layouts.app')

@section('title', 'Convite inválido — '.$product['name'])

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('body')
<div class="login-shell" data-login-page>
    <div class="login-atmosphere" aria-hidden="true"></div>
    <div class="login-frame">
        <section class="login-panel mx-auto max-w-lg">
            <div class="login-panel-inner">
                <h1 class="font-display text-2xl font-bold text-snow">Este convite já não está válido</h1>
                <p class="mt-3 text-sm text-mist">O link expirou, já foi usado, ou não existe. Peça um novo convite à BURI-TI.</p>
            </div>
        </section>
    </div>
</div>
@endsection
