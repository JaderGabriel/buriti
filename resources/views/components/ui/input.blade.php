@props([
    'type' => 'text',
    'name',
    'label',
    'value' => null,
    'required' => false,
])

@php
    $baseClass = 'w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow outline-none ring-brand-bright focus:ring-1';
@endphp

<label {{ $attributes->only('class')->merge(['class' => 'block text-sm']) }}>
    <span class="text-mist">{{ $label }}</span>
    @if($type === 'textarea')
        <textarea
            name="{{ $name }}"
            @required($required)
            {{ $attributes->except('class')->merge(['class' => 'mt-1.5 '.$baseClass]) }}
        >{{ old($name, $value) }}</textarea>
    @elseif($type === 'password')
        <div class="relative mt-1.5" data-password-field>
            <input
                type="password"
                name="{{ $name }}"
                value="{{ old($name, $value) }}"
                @required($required)
                data-password-input
                {{ $attributes->except('class')->merge(['class' => $baseClass.' pr-11']) }}
            >
            <button
                type="button"
                class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center text-mist transition hover:text-snow"
                data-password-toggle
                aria-label="Mostrar senha"
                title="Mostrar senha"
            >
                <x-ui.icon name="eye" class="h-4 w-4" data-password-icon="show" />
                <x-ui.icon name="eye-off" class="hidden h-4 w-4" data-password-icon="hide" />
            </button>
        </div>
    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @required($required)
            {{ $attributes->except('class')->merge(['class' => 'mt-1.5 '.$baseClass]) }}
        >
    @endif
    @if (isset($errors) && $errors->has($name))
        <span class="mt-1 block text-xs text-red-400">{{ $errors->first($name) }}</span>
    @endif
</label>
