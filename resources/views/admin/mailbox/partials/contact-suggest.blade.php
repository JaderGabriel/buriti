<template x-teleport="body">
    <div
        class="mail-suggest"
        x-show="open"
        x-cloak
        x-ref="suggest"
        @mousedown.prevent
        :style="suggestStyle()"
        role="listbox"
        aria-label="Contatos"
    >
        <p class="mail-suggest__status" x-show="loading && hits.length === 0" x-cloak>Buscando contatos…</p>
        <template x-for="(hit, index) in hits" :key="(hit.id || hit.email) + '-' + index">
            <button
                type="button"
                class="mail-suggest__hit"
                :class="{ 'is-active': active === index }"
                role="option"
                @mousedown.prevent="pick(hit)"
                @mouseenter="active = index"
            >
                <span class="mail-suggest__avatar" x-text="initial(hit)"></span>
                <span class="mail-suggest__meta">
                    <span class="mail-suggest__name" x-text="hit.name || hit.email"></span>
                    <span class="mail-suggest__detail">
                        <span x-text="hit.email"></span>
                        <span x-show="hit.company" x-text="' · ' + hit.company"></span>
                    </span>
                </span>
            </button>
        </template>
        <p
            class="mail-suggest__status"
            x-show="!loading && query.trim() !== '' && hits.length === 0"
            x-cloak
        >
            Nenhum contato. Enter adiciona o e-mail escrito.
        </p>
        <p
            class="mail-suggest__status"
            x-show="!loading && query.trim() === '' && hits.length === 0"
            x-cloak
        >
            Nenhum contato com e-mail no sistema.
        </p>
    </div>
</template>
