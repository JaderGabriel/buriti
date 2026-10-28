@extends('layouts.testimonial')

@section('title', 'Depoimento — '.$product['name'])

@section('card')
<p class="testimonial-page__eyebrow">
    <x-ui.icon name="quote" class="h-3.5 w-3.5" />
    Convite
</p>
<h1 class="testimonial-page__title">Como foi trabalhar conosco?</h1>
<p class="testimonial-page__lead">
    Olá{{ $testimonial->contact?->name ? ', '.$testimonial->contact->name : '' }}.
    Seu comentário e as estrelas ficam selados. A BURI-TI pode mediar a versão publicada, mas o texto original permanece verificável.
</p>
@if($testimonial->project)
    <p class="testimonial-page__chip">
        <x-ui.icon name="project" class="h-3.5 w-3.5" />
        {{ $testimonial->project->name }}
    </p>
@endif

<form method="POST" action="{{ route('testimonials.submit', $token) }}" class="mt-7 space-y-5">
    @csrf
    <fieldset>
        <legend class="mb-2 text-sm text-mist">Sua avaliação</legend>
        <div class="testimonial-rate">
            @for($i = 5; $i >= 1; $i--)
                <input
                    type="radio"
                    id="star-{{ $i }}"
                    name="stars"
                    value="{{ $i }}"
                    @checked((int) old('stars') === $i)
                    required
                >
                <label for="star-{{ $i }}" title="{{ $i }} estrela{{ $i === 1 ? '' : 's' }}">
                    <span class="sr-only">{{ $i }} estrela{{ $i === 1 ? '' : 's' }}</span>
                    <x-ui.icon name="star" class="h-8 w-8" />
                </label>
            @endfor
        </div>
        @error('stars') <p class="mt-2 text-xs text-red-300">{{ $message }}</p> @enderror
    </fieldset>

    <label class="block text-sm">
        <span class="text-mist">Como você prefere ser identificado</span>
        <input type="text" name="display_name" value="{{ old('display_name', $defaultName) }}" maxlength="120" class="login-input" autocomplete="nickname">
    </label>

    <label class="block text-sm">
        <span class="text-mist">Seu depoimento</span>
        <textarea name="body" rows="7" maxlength="4000" required class="login-input min-h-40">{{ old('body') }}</textarea>
        @error('body') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
    </label>

    <button type="submit" class="login-submit inline-flex items-center justify-center gap-2">
        <x-ui.icon name="qualified" class="h-4 w-4" />
        Enviar e selar
    </button>
</form>
@endsection
