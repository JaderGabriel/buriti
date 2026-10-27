@extends('layouts.app')

@section('title', 'Verificação — BURI-TI')

@section('body')
<div class="login-shell" data-login-page>
    <div class="login-atmosphere" aria-hidden="true"></div>

    <div class="login-frame">
        <aside class="login-brand">
            <a href="{{ route('home') }}" class="login-brand-mark">
                <img src="{{ asset('images/logo-buriti.png') }}" alt="BURI-TI" class="h-14 w-14 object-contain sm:h-16 sm:w-16">
                <div>
                    <p class="font-display text-xl font-bold tracking-[0.14em] text-snow sm:text-2xl">BURI-TI</p>
                    <p class="mt-1 text-[11px] uppercase tracking-[0.22em] text-mist">Tecnologia para Pessoas</p>
                </div>
            </a>

            <div class="mt-auto hidden lg:block">
                <p class="font-display text-3xl font-bold leading-tight text-snow">Quase lá</p>
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-mist">
                    Enviamos um código de 6 dígitos no Telegram vinculado à sua conta.
                </p>
            </div>
        </aside>

        <section class="login-panel" aria-labelledby="otp-heading">
            <div class="login-panel-inner login-panel-inner--otp">
                <div class="mb-6 text-center sm:mb-8">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-bright">Verificação em dois fatores</p>
                    <h1 id="otp-heading" class="mt-2 font-display text-2xl font-bold text-snow sm:text-3xl">Digite o código</h1>
                    <p class="mt-2 text-sm text-mist">Abra o Telegram e informe os 6 dígitos. O acesso conclui sozinho ao completar.</p>
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
                    novalidate
                >
                    @csrf
                    <input
                        type="text"
                        name="code"
                        id="otp-code"
                        class="otp-value"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        pattern="[0-9]*"
                        aria-label="Código de 6 dígitos"
                        data-otp-value
                        value="{{ old('code') }}"
                    >

                    <div class="otp-track" role="group" aria-label="Dígitos do código" data-otp-track>
                        @for ($i = 0; $i < 6; $i++)
                            @if ($i === 3)
                                <span class="otp-gap" aria-hidden="true"></span>
                            @endif
                            <button
                                type="button"
                                class="otp-cell"
                                data-otp-cell
                                data-index="{{ $i }}"
                                aria-label="Dígito {{ $i + 1 }}"
                            ></button>
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
                    <form method="POST" action="{{ route('login.two-factor.resend') }}">
                        @csrf
                        <button
                            type="submit"
                            class="font-semibold text-brand-bright transition hover:text-snow disabled:cursor-not-allowed disabled:opacity-50"
                            data-otp-resend
                            data-otp-resend-wait="{{ (int) $resendWait }}"
                            @disabled($resendWait > 0)
                        >
                            Reenviar código
                        </button>
                    </form>

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
