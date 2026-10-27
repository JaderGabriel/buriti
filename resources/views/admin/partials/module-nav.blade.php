@php
    $modules = $modules ?? [];
@endphp
<nav class="admin-nav" aria-label="Módulos">
    @foreach($modules as $module)
        @php $active = \App\Support\AdminNavigation::matches($module['pattern']); @endphp
        <div @class(['admin-nav__group', 'admin-nav__group--jirau' => ($module['id'] ?? '') === 'jirau'])>
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
        </div>
    @endforeach
    @isset($runtimeVersions)
        <p class="admin-versions" aria-label="Versões do ambiente">
            <span>Laravel {{ $runtimeVersions['laravel'] }}</span>
            <span>PHP {{ $runtimeVersions['php'] }}</span>
            <span>Server {{ $runtimeVersions['server'] }}</span>
            <span>{{ $runtimeVersions['release'] }}</span>
        </p>
    @endisset
</nav>
