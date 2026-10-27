@props([
    'name',
    'title',
    'eyebrow' => 'Jirau',
    'open' => false,
    'wide' => false,
    'compact' => false,
])

@php
    $dialogId = 'jirau-'.$name.'-dialog';
    $titleId = $dialogId.'-title';
@endphp
<div
    id="{{ $dialogId }}"
    class="jirau-dialog"
    data-jirau-panel="{{ $name }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
    aria-hidden="{{ $open ? 'false' : 'true' }}"
    @unless($open) hidden @endunless
>
    <button type="button" class="jirau-dialog__backdrop" data-jirau-dialog-close aria-label="Fechar"></button>
    <div @class(['jirau-dialog__panel', 'jirau-dialog__panel--wide' => $wide, 'jirau-dialog__panel--compact' => $compact])>
        <div class="jirau-dialog__head">
            <div>
                <p class="jirau-dialog__eyebrow">{{ $eyebrow }}</p>
                <h2 id="{{ $titleId }}">{{ $title }}</h2>
            </div>
            <button type="button" class="jirau-dialog__close" data-jirau-dialog-close aria-label="Fechar">✕</button>
        </div>
        {{ $slot }}
    </div>
</div>
