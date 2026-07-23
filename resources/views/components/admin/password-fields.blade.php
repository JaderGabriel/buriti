@props([
    'editing' => false,
])

<div {{ $attributes->merge(['class' => 'border-t border-line pt-6']) }} data-password-generator>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Senha</p>
            <p class="mt-1 text-xs text-mist">
                {{ $editing ? 'Deixe em branco para manter a senha atual.' : 'Obrigatória na criação. Pode gerar uma senha forte.' }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-sm border border-line px-3 py-2 text-xs font-semibold text-snow transition hover:border-brand-bright/50"
                data-password-generate
            >
                Gerar senha aleatória
            </button>
            <button
                type="button"
                class="rounded-sm border border-line px-3 py-2 text-xs font-semibold text-mist transition hover:border-brand-bright/50 hover:text-snow disabled:cursor-not-allowed disabled:opacity-40"
                data-password-copy
                disabled
            >
                Copiar
            </button>
        </div>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="block text-sm">
            <span class="text-mist">{{ $editing ? 'Nova senha (opcional)' : 'Senha' }}</span>
            <div class="relative mt-1.5" data-password-field>
                <input
                    type="password"
                    name="password"
                    @required(! $editing)
                    autocomplete="new-password"
                    data-password-input
                    data-password-value
                    class="w-full rounded-xl border border-line bg-ink px-3 py-2.5 pr-11 text-snow outline-none ring-brand-bright focus:ring-1"
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
            @if(isset($errors) && $errors->has('password'))
                <span class="mt-1 block text-xs text-red-400">{{ $errors->first('password') }}</span>
            @endif
        </label>

        <label class="block text-sm">
            <span class="text-mist">Confirmar senha</span>
            <div class="relative mt-1.5" data-password-field>
                <input
                    type="password"
                    name="password_confirmation"
                    @required(! $editing)
                    autocomplete="new-password"
                    data-password-input
                    data-password-confirm
                    class="w-full rounded-xl border border-line bg-ink px-3 py-2.5 pr-11 text-snow outline-none ring-brand-bright focus:ring-1"
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
    </div>

    <div
        data-password-generated
        class="mt-3 hidden rounded-sm border border-brand/30 bg-brand/10 px-3 py-3 text-sm text-snow"
        hidden
    >
        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-brand">Senha gerada</p>
        <p class="mt-2 break-all font-mono text-base tracking-wide" data-password-display></p>
        <p class="mt-2 text-xs text-mist">
            Os dois campos foram preenchidos. Guarde a senha num sítio seguro antes de salvar — depois só o hash fica no sistema.
        </p>
    </div>
</div>
