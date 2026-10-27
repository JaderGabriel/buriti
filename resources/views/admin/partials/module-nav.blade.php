@php
    $modules = $modules ?? [];
@endphp
<nav class="admin-nav" aria-label="Módulos">
    @foreach($modules as $module)
        @php
            $active = \App\Support\AdminNavigation::matches($module['pattern']);
            $items = $module['items'] ?? [];
            $showItems = count($items) > 1
                || (count($items) === 1 && ($items[0]['route'] ?? null) !== ($module['route'] ?? null));
        @endphp
        <div @class(['admin-nav__group', 'admin-nav__group--'.($module['id'] ?? 'home')])>
            <a
                href="{{ route($module['route']) }}"
                data-admin-close
                title="{{ $module['label'] }}"
                @class(['admin-nav__link', 'is-active' => $active])
            >
                <span class="admin-nav__icon">
                    <x-ui.icon :name="$module['icon']" class="h-4 w-4" />
                </span>
                <span class="admin-nav__text">{{ $module['label'] }}</span>
                @if(! empty($module['badge']))
                    <span class="admin-nav__badge">{{ $module['badge'] > 99 ? '99+' : $module['badge'] }}</span>
                @endif
            </a>
            @if($showItems)
                <div class="admin-nav__sub">
                    @foreach($items as $item)
                        @php
                            $href = $item['url'] ?? route($item['route']);
                            $itemActive = array_key_exists('active', $item)
                                ? (bool) $item['active']
                                : (is_array($item['pattern']) ? request()->routeIs(...$item['pattern']) : request()->routeIs($item['pattern']));
                        @endphp
                        <a
                            href="{{ $href }}"
                            data-admin-close
                            title="{{ $item['label'] }}"
                            @class(['admin-nav__sublink', 'is-active' => $itemActive])
                        >
                            <span class="admin-nav__subicon" aria-hidden="true">
                                <x-ui.icon :name="$item['icon'] ?? 'flow'" class="h-4 w-4" />
                            </span>
                            <span>{{ $item['label'] }}</span>
                            @if(! empty($item['badge']))
                                <span class="admin-nav__badge">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
    @isset($runtimeVersions)
        <p class="admin-versions" aria-label="Versões do ambiente">
            <span>Laravel {{ $runtimeVersions['laravel'] }}</span>
            <span>PHP {{ $runtimeVersions['php'] }}</span>
            <span>Server {{ $runtimeVersions['server'] }}</span>
            <span><x-product.release /></span>
        </p>
    @endisset
</nav>
