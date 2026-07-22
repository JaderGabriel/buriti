@props([
    'href' => '#',
    'variant' => 'primary',
    'type' => null,
])

@php
    $classes = match ($variant) {
        'secondary' => 'border border-line text-snow hover:border-brand-bright/50 hover:text-brand-bright',
        'danger' => 'border border-red-500/40 text-red-300 hover:bg-red-500/10',
        'ghost' => 'text-mist hover:text-snow',
        'light' => 'bg-snow text-ink hover:bg-white',
        default => 'bg-brand text-white hover:bg-brand-bright shadow-[0_10px_40px_rgba(30,112,191,0.25)]',
    };
@endphp

@if($type)
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold transition $classes"]) }}>
        {{ $slot }}
    </button>
@else
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "inline-flex items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold transition $classes"]) }}>
        {{ $slot }}
    </a>
@endif
