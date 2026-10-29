<div class="mail-picker" @mouseenter="keepOpen()">
    <input type="hidden" name="{{ $field }}" :value="joined('{{ $field }}')">
    <div class="mail-chips" @click="$refs.{{ $field }}Query?.focus()">
        <template x-for="(chip, index) in chips.{{ $field }}" :key="chip.email + '-' + index">
            <span class="mail-token">
                <span class="mail-token__label" x-text="chip.name || chip.email"></span>
                <span class="mail-token__email" x-show="chip.name" x-text="chip.email"></span>
                <button type="button" class="mail-token__remove" @click.stop="removeChip('{{ $field }}', index)" :aria-label="'Remover ' + (chip.email || '')">×</button>
            </span>
        </template>
        <input
            id="{{ $id }}"
            type="text"
            class="mail-chips__input"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            autocorrect="off"
            spellcheck="false"
            x-model="query.{{ $field }}"
            x-ref="{{ $field }}Query"
            @focus="lookup('{{ $field }}')"
            @input="lookup('{{ $field }}')"
            @keydown="onKey('{{ $field }}', $event)"
            @blur="onBlur('{{ $field }}')"
            @if($field === 'to')
                :required="chips.to.length === 0"
            @endif
        >
    </div>
    @include('admin.mailbox.partials.contact-suggest', ['field' => $field])
</div>
