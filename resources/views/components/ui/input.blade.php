@props([
    'type' => 'text',
    'name',
    'label',
    'value' => null,
    'required' => false,
])

<label class="block text-sm">
    <span class="text-mist">{{ $label }}</span>
    @if($type === 'textarea')
        <textarea
            name="{{ $name }}"
            @required($required)
            {{ $attributes->merge(['class' => 'mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow outline-none ring-brand-bright focus:ring-1']) }}
        >{{ old($name, $value) }}</textarea>
    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @required($required)
            {{ $attributes->merge(['class' => 'mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow outline-none ring-brand-bright focus:ring-1']) }}
        >
    @endif
    @if (isset($errors) && $errors->has($name))
        <span class="mt-1 block text-xs text-red-400">{{ $errors->first($name) }}</span>
    @endif
</label>
