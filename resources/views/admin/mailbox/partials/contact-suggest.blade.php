<ul
    class="mail-suggest"
    x-show="open.{{ $field }} && hits.{{ $field }}.length"
    x-cloak
    role="listbox"
    aria-label="Contatos"
>
    <template x-for="hit in hits.{{ $field }}" :key="hit.id">
        <li>
            <button
                type="button"
                class="mail-suggest__hit"
                @mousedown.prevent="pick('{{ $field }}', hit.email)"
            >
                <span class="mail-suggest__name" x-text="hit.name"></span>
                <span class="mail-suggest__email" x-text="hit.email"></span>
                <span class="mail-suggest__company" x-show="hit.company" x-text="hit.company"></span>
            </button>
        </li>
    </template>
</ul>
