@extends('layouts.app')

@section('title', 'Depoimento — '.$product['name'])

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('body')
<div class="login-shell" data-login-page>
    <div class="login-atmosphere" aria-hidden="true"></div>
    <div class="login-frame">
        <aside class="login-brand">
            <p class="font-display text-xl font-bold tracking-[0.14em] text-snow">{{ $product['name'] }}</p>
            <p class="mt-3 max-w-sm text-sm leading-relaxed text-mist">
                O seu comentário e as estrelas ficam selados. A BURI-TI pode mediar a versão publicada, mas o texto original permanece verificável.
            </p>
        </aside>
        <section class="login-panel">
            <div class="login-panel-inner">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-bright">Depoimento</p>
                <h1 class="mt-2 font-display text-2xl font-bold text-snow">Como foi trabalhar connosco?</h1>
                <p class="mt-2 text-sm text-mist">
                    Olá{{ $testimonial->contact?->name ? ', '.$testimonial->contact->name : '' }}.
                    @if($testimonial->project)
                        Referente a {{ $testimonial->project->name }}.
                    @endif
                </p>

                <form method="POST" action="{{ route('testimonials.submit', $token) }}" class="mt-6 space-y-4">
                    @csrf
                    <fieldset>
                        <legend class="mb-2 text-sm text-mist">Estrelas</legend>
                        <div class="flex flex-wrap gap-2">
                            @for($i = 1; $i <= 5; $i++)
                                <label class="inline-flex cursor-pointer items-center gap-1 rounded-sm border border-line px-3 py-2 text-sm">
                                    <input type="radio" name="stars" value="{{ $i }}" @checked((int) old('stars') === $i) required>
                                    {{ $i }} ★
                                </label>
                            @endfor
                        </div>
                        @error('stars') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                    </fieldset>

                    <label class="block text-sm">
                        <span class="mb-1 block text-mist">Como prefere ser identificado</span>
                        <input type="text" name="display_name" value="{{ old('display_name', $defaultName) }}" maxlength="120" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block text-mist">O seu depoimento</span>
                        <textarea name="body" rows="7" maxlength="4000" required class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">{{ old('body') }}</textarea>
                        @error('body') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                    </label>

                    <button type="submit" class="inline-flex min-h-11 items-center rounded-full bg-brand px-5 text-sm font-semibold text-white">
                        Enviar e selar
                    </button>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection
