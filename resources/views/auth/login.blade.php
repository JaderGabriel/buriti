@extends('layouts.app')

@section('title', 'Login — BURI-TI')

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
                <p class="font-display text-3xl font-bold leading-tight text-snow">Painel operacional</p>
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-mist">
                    Mensagens, projetos, agenda e CRM — com o mesmo cuidado visual do site institucional.
                </p>
            </div>
        </aside>

        <section class="login-panel" aria-labelledby="login-heading">
            <div class="login-panel-inner">
                <div class="mb-8">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-bright">Acesso admin</p>
                    <h1 id="login-heading" class="mt-2 font-display text-2xl font-bold text-snow sm:text-3xl">Entrar no painel</h1>
                    <p class="mt-2 text-sm text-mist">Use e-mail/username ou confirme pelo Telegram.</p>
                </div>

                @if ($errors->has('telegram'))
                    <div class="mb-5 rounded-sm border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-sm text-red-300" role="alert">
                        {{ $errors->first('telegram') }}
                    </div>
                @endif

                @if ($telegramLoginEnabled)
                    <div class="space-y-3" data-telegram-login>
                        <button
                            type="button"
                            class="login-telegram-btn"
                            data-telegram-start
                            data-start-url="{{ route('login.telegram.start') }}"
                        >
                            <x-ui.icon name="telegram" class="h-5 w-5" />
                            <span data-telegram-label>Continuar com Telegram</span>
                        </button>

                        <div class="hidden rounded-sm border border-line bg-ink/60 px-3 py-3 text-sm text-mist" data-telegram-wait hidden>
                            <p class="font-medium text-snow">Aguardando confirmação no Telegram…</p>
                            <p class="mt-1 text-xs leading-relaxed">
                                Abra o bot, confirme o pedido e volte aqui. O painel abre automaticamente.
                            </p>
                            <a href="#" class="mt-2 inline-flex text-xs font-semibold text-brand-bright hover:text-snow" data-telegram-open target="_blank" rel="noopener">
                                Abrir Telegram de novo
                            </a>
                        </div>

                        @if ($telegramBotUsername)
                            <div class="flex justify-center pt-1" data-telegram-widget-host>
                                <script
                                    async
                                    src="https://telegram.org/js/telegram-widget.js?22"
                                    data-telegram-login="{{ $telegramBotUsername }}"
                                    data-size="large"
                                    data-radius="4"
                                    data-auth-url="{{ route('login.telegram.callback') }}"
                                    data-request-access="write"
                                ></script>
                            </div>
                        @endif
                    </div>

                    <div class="login-divider" aria-hidden="true">
                        <span>ou com senha</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf
                    <label class="block text-sm">
                        <span class="text-mist">E-mail ou username</span>
                        <input
                            type="text"
                            name="login"
                            value="{{ old('login') }}"
                            required
                            autofocus
                            autocomplete="username"
                            class="login-input"
                        >
                        @error('login') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm">
                        <span class="text-mist">Senha</span>
                        <div class="relative mt-1.5" data-password-field>
                            <input
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                data-password-input
                                class="login-input pr-11"
                            >
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center text-mist transition hover:text-snow"
                                data-password-toggle
                                aria-label="Mostrar senha"
                                title="Mostrar senha"
                            >
                                <x-ui.icon name="eye" class="h-4 w-4" data-password-icon="show" />
                                <x-ui.icon name="eye-off" class="hidden h-4 w-4" data-password-icon="hide" />
                            </button>
                        </div>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-mist">
                        <input type="checkbox" name="remember" value="1" class="rounded border-line accent-[var(--color-brand)]">
                        Lembrar-me neste dispositivo
                    </label>
                    <button type="submit" class="login-submit">Entrar</button>
                </form>

                <p class="mt-8 text-center text-xs text-mist">
                    <a href="{{ route('home') }}" class="font-medium text-snow/80 transition hover:text-brand-bright">← Voltar ao site</a>
                </p>
            </div>
        </section>
    </div>
</div>
@endsection
