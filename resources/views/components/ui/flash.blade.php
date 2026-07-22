@props(['message' => null, 'type' => 'success'])

@php
    $text = $message ?? session($type === 'success' ? 'success' : 'contact_success');
    $classes = $type === 'success'
        ? 'border-brand/40 bg-brand/10 text-brand-bright'
        : 'border-red-500/40 bg-red-500/10 text-red-300';
@endphp

@if($text)
    <div {{ $attributes->merge(['class' => "mb-5 rounded-xl border px-4 py-3 text-sm $classes"]) }} role="status">
        {{ $text }}
    </div>
@endif
