@props([
    'tone' => 'neutral',
    'icon' => 'plus',
    'href' => null,
])

@php
    $classes = 'jirau-action jirau-action--'.$tone;
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <x-ui.icon :name="$icon" class="h-4 w-4" />
        <span>{{ $slot }}</span>
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>
        <x-ui.icon :name="$icon" class="h-4 w-4" />
        <span>{{ $slot }}</span>
    </button>
@endif
