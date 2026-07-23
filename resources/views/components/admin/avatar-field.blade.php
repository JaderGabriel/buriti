@props([
    'url' => null,
    'initials' => '?',
    'size' => 'md',
    'inputName' => 'avatar',
    'inputId' => null,
])

@php
    $sizes = [
        'sm' => 'h-16 w-16 text-lg',
        'md' => 'h-20 w-20 text-2xl sm:h-24 sm:w-24 sm:text-3xl',
        'lg' => 'h-24 w-24 text-3xl',
    ];
    $box = $sizes[$size] ?? $sizes['md'];
    $inputId = $inputId ?: 'avatar-'.uniqid();
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-center']) }} data-avatar-preview>
    <div class="shrink-0">
        <img
            data-avatar-preview-image
            data-original-src="{{ $url ?: '' }}"
            src="{{ $url ?: '' }}"
            alt=""
            @class([
                $box,
                'rounded-sm object-cover ring-1 ring-line',
                'hidden' => ! $url,
            ])
            @if(! $url) hidden @endif
        >
        <span
            data-avatar-preview-fallback
            @class([
                'inline-flex items-center justify-center rounded-sm border border-brand/40 bg-ink/40 font-display font-bold text-brand-bright',
                $box,
                'hidden' => (bool) $url,
            ])
            @if($url) hidden @endif
        >{{ $initials }}</span>
    </div>

    <div class="min-w-0 flex-1">
        {{ $slot }}

        <label for="{{ $inputId }}" class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-mist transition hover:border-brand-bright/40 hover:text-snow">
            <span data-avatar-preview-name>Escolher arquivo</span>
            <input
                id="{{ $inputId }}"
                type="file"
                name="{{ $inputName }}"
                accept="image/*"
                class="sr-only"
                data-avatar-preview-input
            >
        </label>
    </div>
</div>
