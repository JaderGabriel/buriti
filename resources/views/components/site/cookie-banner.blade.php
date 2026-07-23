<div
    id="cookie-banner"
    class="fixed inset-x-0 bottom-0 z-[90] hidden p-4 sm:p-6"
    role="dialog"
    aria-live="polite"
    aria-label="Aviso de cookies"
    hidden
>
    <div class="mx-auto flex max-w-4xl flex-col gap-4 rounded-sm border border-line bg-panel p-4 shadow-2xl sm:flex-row sm:items-end sm:gap-6 sm:p-5">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-snow">Cookies e privacidade</p>
            <p class="mt-2 text-sm leading-relaxed text-mist">
                Usamos cookies e armazenamento local <strong class="text-snow">necessários</strong> à segurança do site,
                à sessão do painel e à preferência de tema. Não usamos cookies de publicidade.
                Detalhes em
                <a href="{{ route('cookies') }}" class="font-semibold text-brand-bright hover:underline">Cookies</a>
                e
                <a href="{{ route('privacy') }}" class="font-semibold text-brand-bright hover:underline">Privacidade</a>
                (LGPD).
            </p>
        </div>
        <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
            <a href="{{ route('cookies') }}" class="inline-flex items-center justify-center rounded-sm border border-line px-4 py-2.5 text-sm font-semibold text-mist transition hover:border-brand-bright/40 hover:text-snow">
                Saiba mais
            </a>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-sm bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-bright"
                data-cookie-accept
            >
                Concordo e Aceito
            </button>
        </div>
    </div>
</div>
