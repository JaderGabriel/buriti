@props([
    'label' => 'Área admin',
    'class' => '',
])

<a
    href="{{ route('admin.dashboard') }}"
    {{ $attributes->merge(['class' => $class]) }}
>
    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        {{ $label }}
    @endif
</a>
