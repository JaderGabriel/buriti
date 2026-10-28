@extends('layouts.testimonial')

@section('title', 'Depoimento enviado — '.$product['name'])

@section('card')
<p class="testimonial-page__eyebrow">
    <x-ui.icon name="qualified" class="h-3.5 w-3.5" />
    Obrigado
</p>
<h1 class="testimonial-page__title">Depoimento selado</h1>
<p class="testimonial-page__lead">
    O texto que você enviou foi registrado com um selo criptográfico. A equipe pode mediar uma versão para publicação futura, mas o original não é alterado.
</p>
@if($testimonial->verification_code)
    <p class="testimonial-page__code">{{ $testimonial->verification_code }}</p>
    <p class="mt-2 text-xs text-mist">Guarde este código. Serve para confirmar a autenticidade quando o depoimento for usado.</p>
@endif
@endsection
