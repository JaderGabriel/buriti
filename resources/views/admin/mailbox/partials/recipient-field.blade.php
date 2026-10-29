@php
    $seedEmail = trim((string) ($seedEmail ?? ''));
    $seedName = trim((string) ($seedName ?? ''));
@endphp
<div class="mail-picker" @mouseenter="keepOpen()">
    <input type="hidden" name="{{ $field }}" value="{{ $seedEmail }}" :value="joined('{{ $field }}')">
    <div class="mail-chips" @click="$refs.{{ $field }}Query?.focus()">
        @if($seedEmail !== '')
            <span class="mail-token" data-mail-seed-chip title="{{ $seedEmail }}" x-show="chips.{{ $field }}.length === 0">
                <span class="mail-token__label">{{ $seedName !== '' ? $seedName : $seedEmail }}</span>
            </span>
        @endif
        <template x-for="(chip, index) in chips.{{ $field }}" :key="chip.email + '-' + index">
            <span class="mail-token" :title="chip.email">
                <span class="mail-token__label" x-text="chip.name || chip.email"></span>
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
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="open.{{ $field }} ? 'true' : 'false'"
            x-model="query.{{ $field }}"
            x-ref="{{ $field }}Query"
            @focus="lookup('{{ $field }}', $event.target.value)"
            @input="lookup('{{ $field }}', $event.target.value)"
            @keydown="onKey('{{ $field }}', $event)"
            @blur="onBlur('{{ $field }}')"
            @if($field === 'to')
                :required="chips.to.length === 0"
            @endif
        >
    </div>
    @include('admin.mailbox.partials.contact-suggest', ['field' => $field])
</div>
