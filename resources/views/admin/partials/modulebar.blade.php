@php
    $moduleItems = $moduleItems ?? [];
    $moduleLabel = $moduleLabel ?? null;
@endphp
@if($moduleItems !== [])
    <nav class="admin-modulebar" aria-label="{{ $moduleLabel ?: 'Módulo' }}">
        @if($moduleLabel)
            <p class="admin-modulebar__label">{{ $moduleLabel }}</p>
        @endif
        @foreach($moduleItems as $item)
            @php
                $href = $item['url'] ?? route($item['route']);
                $active = array_key_exists('active', $item)
                    ? (bool) $item['active']
                    : (is_array($item['pattern']) ? request()->routeIs(...$item['pattern']) : request()->routeIs($item['pattern']));
            @endphp
            <a
                href="{{ $href }}"
                @class(['admin-modulebar__link', 'is-active' => $active])
            >
                <span class="admin-modulebar__icon" aria-hidden="true">
                    <x-ui.icon :name="$item['icon']" class="h-4 w-4" />
                </span>
                <span>{{ $item['label'] }}</span>
                @if(! empty($item['badge']))
                    <span class="admin-nav__badge">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>
@endif
