@php
    $currentModule = $currentModule ?? null;
@endphp
@if($currentModule)
    <a
        href="{{ route($currentModule['route']) }}"
        class="admin-icon-btn admin-icon-btn--module"
        data-admin-module-pin
        data-admin-close
        title="{{ $currentModule['label'] }}"
        aria-label="Módulo {{ $currentModule['label'] }}"
        aria-current="page"
    >
        <x-ui.icon :name="$currentModule['icon']" class="h-4 w-4" />
        @if(! empty($currentModule['badge']))
            <span class="admin-nav__badge admin-nav__badge--pin">{{ $currentModule['badge'] > 99 ? '99+' : $currentModule['badge'] }}</span>
        @endif
    </a>
@endif
