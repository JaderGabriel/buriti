@props([
    'tone' => 'neutral',
    'icon' => 'plus',
    'href' => null,
    'iconOnly' => false,
])

@php
    $label = trim((string) $slot);
    $classes = 'jirau-action jirau-action--'.$tone.($iconOnly || $label === '' ? ' jirau-action--icon' : '');
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <x-ui.icon :name="$icon" class="h-4 w-4" />
        @if($label !== '')
            <span>{{ $label }}</span>
        @endif
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        <x-ui.icon :name="$icon" class="h-4 w-4" />
        @if($label !== '')
            <span>{{ $label }}</span>
        @endif
    </button>
@endif
