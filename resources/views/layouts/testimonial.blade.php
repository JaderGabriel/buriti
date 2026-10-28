@extends('layouts.app')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('body')
<div class="testimonial-page">
    <div class="login-atmosphere" aria-hidden="true"></div>
    <div class="testimonial-page__frame">
        <header class="testimonial-page__brand">
            <span class="login-brand-mark">
                <img src="{{ asset('images/logo-buriti.png') }}" alt="{{ $product['name'] }}" class="h-12 w-12 object-contain sm:h-14 sm:w-14">
                <div>
                    <p class="font-display text-xl font-bold tracking-[0.14em] text-snow">{{ $product['name'] }}</p>
                    <p class="mt-1 text-[11px] uppercase tracking-[0.22em] text-mist">Depoimento</p>
                </div>
            </span>
        </header>
        <div class="testimonial-page__card">
            @yield('card')
        </div>
    </div>
</div>
@endsection
