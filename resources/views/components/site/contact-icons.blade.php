@props([
    'email' => null,
    'whatsapp' => null,
    'linkedin' => null,
    'github' => null,
    'telegram' => null,
    'telegramHandle' => null,
    'site' => null,
])

@php
    $waHref = \App\Support\WhatsAppLink::href(is_string($whatsapp) ? $whatsapp : null);
    $waLabel = \App\Support\WhatsAppLink::label(is_string($whatsapp) ? $whatsapp : null);
    $emailHref = filled($email) ? 'mailto:'.$email : null;
    $iconClass = 'inline-flex h-11 w-11 items-center justify-center rounded-sm border border-line text-brand-bright transition hover:border-brand-bright/50 hover:bg-ink/40 hover:text-snow';
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3']) }}>
    @if($emailHref)
        <a
            href="{{ $emailHref }}"
            class="{{ $iconClass }}"
            aria-label="Enviar e-mail{{ $email ? ' para '.$email : '' }}"
            title="E-mail{{ $email ? ': '.$email : '' }}"
        >
            <x-ui.icon name="mail" class="h-5 w-5" />
        </a>
    @endif

    @if($waHref)
        <a
            href="{{ $waHref }}"
            target="_blank"
            rel="noopener"
            class="{{ $iconClass }}"
            aria-label="Abrir WhatsApp{{ $waLabel ? ' '.$waLabel : '' }}"
            title="WhatsApp{{ $waLabel ? ': '.$waLabel : '' }}"
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
            class="{{ $iconClass }}"
            aria-label="Abrir Telegram{{ $telegramHandle ? ' '.$telegramHandle : '' }}"
            title="Telegram{{ $telegramHandle ? ' '.$telegramHandle : '' }}"
        >
            <x-ui.icon name="telegram" class="h-5 w-5" />
        </a>
    @endif

    {{ $slot }}
</div>
