@props([
    'href' => '#',
    'variant' => 'primary',
    'type' => null,
])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-line text-snow hover:border-brand-bright hover:text-brand-bright',
        'danger' => 'border border-red-500/40 text-red-300 hover:bg-red-500/10',
        'ghost' => 'text-mist hover:text-snow',
        'light' => 'bg-snow text-ink hover:opacity-90',
        default => 'bg-brand text-white hover:bg-brand-bright',
    };
@endphp

@if($type)
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-sm px-5 py-2.5 text-sm font-semibold transition $classes"]) }}>
        {{ $slot }}
    </button>
@else
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-sm px-5 py-2.5 text-sm font-semibold transition $classes"]) }}>
        {{ $slot }}
    </a>
@endif
