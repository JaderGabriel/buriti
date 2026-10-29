<ul
    class="mail-suggest"
    x-show="open.{{ $field }} && hits.{{ $field }}.length > 0"
    x-cloak
    role="listbox"
    aria-label="Contatos"
>
    <template x-for="(hit, index) in hits.{{ $field }}" :key="(hit.id || hit.email) + '-' + index">
        <li>
            <button
                type="button"
                class="mail-suggest__hit"
                :class="{ 'is-active': active.{{ $field }} === index }"
                role="option"
                @mousedown.prevent="pick('{{ $field }}', hit)"
                @mouseenter="active.{{ $field }} = index"
            >
                <span class="mail-suggest__avatar" x-text="((hit.name || hit.email || '?').charAt(0) || '?').toUpperCase()"></span>
                <span class="mail-suggest__meta">
                    <span class="mail-suggest__name" x-text="hit.name || hit.email"></span>
                    <span class="mail-suggest__detail">
                        <span x-text="hit.email"></span>
                        <span x-show="hit.company" x-text="' · ' + hit.company"></span>
                    </span>
                </span>
            </button>
        </li>
    </template>
</ul>
<p
    class="mail-suggest mail-suggest--empty"
    x-show="open.{{ $field }} && query.{{ $field }}.trim() !== '' && hits.{{ $field }}.length === 0"
    x-cloak
>
    Nenhum contato. Enter adiciona o e-mail escrito.
</p>
