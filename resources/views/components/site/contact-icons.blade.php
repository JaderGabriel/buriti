@props([
    'email' => null,
    'phone' => null,
    'whatsapp' => null,
    'linkedin' => null,
    'github' => null,
    'telegram' => null,
    'telegramHandle' => null,
    'site' => null,
])

@php
    $phoneValue = $phone ?: $whatsapp;
    $telHref = $phoneValue ? 'tel:'.preg_replace('/\s+/', '', $phoneValue) : null;
    $waHref = $whatsapp ? 'https://wa.me/'.preg_replace('/\D+/', '', $whatsapp) : null;
    $iconClass = 'inline-flex h-11 w-11 items-center justify-center rounded-sm border border-line text-brand-bright transition hover:border-brand-bright/50 hover:bg-ink/40 hover:text-snow';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3']) }}>
    @if($email)
        <a
            href="mailto:{{ $email }}"
            class="{{ $iconClass }}"
            aria-label="Enviar e-mail"
            title="E-mail"
        >
            <x-ui.icon name="mail" class="h-5 w-5" />
        </a>
    @endif

    @if($telHref)
        <a
            href="{{ $telHref }}"
            class="{{ $iconClass }}"
            aria-label="Ligar"
            title="Telefone"
        >
            <x-ui.icon name="phone" class="h-5 w-5" />
        </a>
    @endif

    @if($waHref)
        <a
            href="{{ $waHref }}"
            target="_blank"
            rel="noopener"
            class="{{ $iconClass }}"
            aria-label="Abrir WhatsApp"
            title="WhatsApp"
        >
            <x-ui.icon name="whatsapp" class="h-5 w-5" />
        </a>
    @endif

    @if($linkedin)
        <a
            href="{{ $linkedin }}"
            target="_blank"
            rel="noopener"
            class="{{ $iconClass }}"
            aria-label="Abrir LinkedIn"
            title="LinkedIn"
        >
            <x-ui.icon name="linkedin" class="h-5 w-5" />
        </a>
    @endif

    @if($github)
        <a
            href="{{ $github }}"
            target="_blank"
            rel="noopener"
            class="{{ $iconClass }}"
            aria-label="Abrir GitHub"
            title="GitHub"
        >
            <x-ui.icon name="github" class="h-5 w-5" />
        </a>
    @endif

    @if($site)
        <a
            href="{{ $site }}"
            target="_blank"
            rel="noopener"
            class="{{ $iconClass }}"
            aria-label="Abrir site"
            title="Site"
        >
            <x-ui.icon name="globe" class="h-5 w-5" />
        </a>
    @endif

    @if($telegram)
        <a
            href="{{ $telegram }}"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-2 rounded-sm border border-line px-3 py-2.5 text-sm text-mist transition hover:border-brand-bright/50 hover:bg-ink/40 hover:text-snow"
            aria-label="Abrir Telegram {{ $telegramHandle ?: '' }}"
            title="Telegram"
        >
            <x-ui.icon name="telegram" class="h-5 w-5 text-brand-bright" />
            <span>{{ $telegramHandle ?: 'Telegram' }}</span>
        </a>
    @endif

    {{ $slot }}
</div>
