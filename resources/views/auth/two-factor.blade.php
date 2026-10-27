@extends('layouts.app')

@section('title', 'Verificação — '.$product['name'])

@section('body')
<div class="login-shell" data-login-page>
    <div class="login-atmosphere" aria-hidden="true"></div>

    <div class="login-frame">
        <aside class="login-brand">
            <a href="{{ route('home') }}" class="login-brand-mark">
                <img src="{{ asset('images/logo-buriti.png') }}" alt="{{ $product['name'] }}" class="h-14 w-14 object-contain sm:h-16 sm:w-16">
                <div>
                    <p class="font-display text-xl font-bold tracking-[0.14em] text-snow sm:text-2xl">{{ $product['name'] }}</p>
                    <p class="mt-1 text-[11px] uppercase tracking-[0.22em] text-mist">{{ $product['tagline'] }}</p>
                </div>
            </a>

            <div class="mt-auto hidden lg:block">
                <p class="font-display text-3xl font-bold leading-tight text-snow">Quase lá</p>
            </div>
        </aside>

        <section class="login-panel" aria-labelledby="otp-heading">
            <div class="login-panel-inner login-panel-inner--otp">
                <div class="mb-6 text-center sm:mb-8">
                    <h1 id="otp-heading" class="font-display text-2xl font-bold text-snow sm:text-3xl">Digite o código</h1>
                </div>

                @if (session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2.5 text-sm text-emerald-200" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @error('code')
                    <div class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-sm text-red-300" role="alert">
                        {{ $message }}
                    </div>
                @enderror

                <form
                    method="POST"
                    action="{{ route('login.two-factor.confirm') }}"
                    class="otp-form{{ $errors->has('code') ? ' is-error' : '' }}"
                    data-otp-form
                    data-otp-prefill="{{ old('code', $prefillCode ?? '') }}"
                    autocomplete="on"
                    novalidate
                >
                    @csrf
                    <div class="otp-track" role="group" aria-label="Dígitos do código" data-otp-track>
                        <input
                            type="text"
                            name="code"
                            id="one-time-code"
                            class="otp-value"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            pattern="[0-9]*"
                            autocapitalize="none"
                            autocorrect="off"
                            spellcheck="false"
                            enterkeyhint="done"
                            aria-label="Código de 6 dígitos"
                            data-otp-value
                            value="{{ old('code', $prefillCode ?? '') }}"
                        >
                        @for ($i = 0; $i < 6; $i++)
                            @if ($i === 3)
                                <span class="otp-gap" aria-hidden="true"></span>
                            @endif
                            <span
                                class="otp-cell"
                                data-otp-cell
                                data-index="{{ $i }}"
                                aria-hidden="true"
                            ></span>
                        @endfor
                    </div>

                    <div class="otp-keypad" data-otp-keypad>
                        @foreach (['1','2','3','4','5','6','7','8','9'] as $key)
                            <button type="button" class="otp-key" data-otp-key="{{ $key }}">{{ $key }}</button>
                        @endforeach
                        <button type="button" class="otp-key otp-key--ghost" data-otp-key="back" aria-label="Apagar">
                            ⌫
                        </button>
                        <button type="button" class="otp-key" data-otp-key="0">0</button>
                        <button type="submit" class="otp-key otp-key--confirm" data-otp-key="submit" aria-label="Confirmar">
                            ✓
                        </button>
                    </div>
                </form>

                <div class="mt-6 flex flex-col items-center gap-3 text-sm">
                    @if ($telegramAvailable ?? true)
                        <form method="POST" action="{{ route('login.two-factor.resend') }}">
                            @csrf
                            <button
                                type="submit"
                                class="font-semibold text-brand-bright transition hover:text-snow disabled:cursor-not-allowed disabled:opacity-50"
                                data-otp-resend
                                data-otp-resend-wait="{{ (int) $resendWait }}"
                                @disabled($resendWait > 0)
                            >
                                @if (($preferIos ?? false) && ! ($telegramSent ?? false))
                                    Enviar pelo Telegram
                                @else
                                    Reenviar código
                                @endif
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('login.two-factor.cancel') }}">
                        @csrf
                        <button type="submit" class="text-mist transition hover:text-snow">Usar outra conta</button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
