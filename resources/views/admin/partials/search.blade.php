@props([
    'id' => 'admin-search',
    'class' => '',
    'placeholder' => 'Buscar módulo, contato, empresa…',
])

<form
    method="GET"
    action="{{ route('admin.search') }}"
    class="admin-search {{ $class }}"
    data-admin-search
    role="search"
>
    <label class="sr-only" for="{{ $id }}">Buscar no painel</label>
    <span class="admin-search__icon" aria-hidden="true">
        <x-ui.icon name="search" class="h-4 w-4" />
    </span>
    <input
        id="{{ $id }}"
        type="search"
        name="q"
        value="{{ request()->routeIs('admin.search') ? request('q') : '' }}"
        placeholder="{{ $placeholder }}"
        class="admin-search__input"
        autocomplete="off"
        autocorrect="off"
        spellcheck="false"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded="false"
        aria-controls="{{ $id }}-suggest"
        data-admin-search-input
    >
    <ul
        id="{{ $id }}-suggest"
        class="admin-search__suggest"
        role="listbox"
        hidden
        data-admin-search-list
    ></ul>
</form>
