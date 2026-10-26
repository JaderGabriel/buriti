@php
    $compact = $compact ?? false;
@endphp
<div class="admin-shortcuts {{ $compact ? 'admin-shortcuts--compact' : '' }}">
    <a
        href="{{ route('home') }}"
        class="admin-icon-btn admin-icon-btn--site"
        title="Ver site"
        aria-label="Ver site"
    >
        <x-ui.icon name="external" class="h-4 w-4" />
    </a>
    <a
        href="{{ $instantMeetUrl ?? 'https://meet.google.com/new' }}"
        target="_blank"
        rel="noopener"
        class="admin-icon-btn admin-icon-btn--meet admin-meet-btn"
        title="Novo Meet"
        aria-label="Novo Meet"
    >
        <x-ui.icon name="meet" class="h-5 w-5" />
    </a>
    <button
        type="button"
        data-theme-toggle
        class="admin-icon-btn admin-icon-btn--theme"
        aria-label="Ativar modo escuro"
        aria-pressed="false"
        title="Alternar tema"
    >
        <x-ui.icon name="sun" class="hidden h-4 w-4 dark:block" />
        <x-ui.icon name="moon" class="block h-4 w-4 dark:hidden" />
    </button>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button
            type="submit"
            class="admin-icon-btn admin-icon-btn--logout"
            title="Sair"
            aria-label="Sair"
        >
            <x-ui.icon name="logout" class="h-4 w-4" />
        </button>
    </form>
</div>
