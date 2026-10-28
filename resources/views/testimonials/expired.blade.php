@extends('layouts.testimonial')

@section('title', 'Convite inválido — '.$product['name'])

@section('card')
<p class="testimonial-page__eyebrow">
    <x-ui.icon name="lost" class="h-3.5 w-3.5" />
    Convite
</p>
<h1 class="testimonial-page__title">Este convite não é mais válido</h1>
<p class="testimonial-page__lead">O link expirou, já foi usado ou não existe. Peça um novo convite à BURI-TI.</p>
@endsection
