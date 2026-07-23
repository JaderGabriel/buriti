@props([
    'title' => 'Como configurar',
    'eyebrow' => 'Documentação',
])

<aside {{ $attributes->class('admin-docs') }}>
    <p class="admin-docs__eyebrow">{{ $eyebrow }}</p>
    <h3 class="admin-docs__title">{{ $title }}</h3>
    <div class="admin-docs__body">
        {{ $slot }}
    </div>
</aside>
