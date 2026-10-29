@php
    $seedEmail = trim((string) ($seedEmail ?? ''));
    $seedName = trim((string) ($seedName ?? ''));
@endphp
<div
    class="mail-picker"
    data-mail-picker="{{ $field }}"
    data-seed-email="{{ $seedEmail }}"
    data-seed-name="{{ $seedName }}"
    data-contacts-url="{{ route('admin.mailbox.contacts') }}"
    x-data="window.mailRecipientField()"
>
    <input type="hidden" name="{{ $field }}" value="{{ $seedEmail }}" :value="joined()">
    <div class="mail-chips" x-ref="box" @click="$refs.query?.focus()">
        <template x-for="(chip, index) in chips" :key="chip.email + '-' + index">
            <span class="mail-token" :title="chip.email">
                <span class="mail-token__avatar" x-text="initial(chip)"></span>
                <span class="mail-token__text">
                    <span class="mail-token__label" x-text="chip.name || chip.email"></span>
                    <span class="mail-token__email" x-show="chip.name" x-text="chip.email"></span>
                </span>
                <button type="button" class="mail-token__remove" @click.stop="removeChip(index)" :aria-label="'Remover ' + (chip.email || '')">×</button>
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
            :aria-expanded="open ? 'true' : 'false'"
            x-ref="query"
            @focus="lookup($event.target.value)"
            @input="lookup($event.target.value)"
            @keydown="onKey($event)"
            @keydown.enter.prevent="onEnter($event)"
            @blur="onBlur()"
            @if($field === 'to')
                :required="chips.length === 0"
            @endif
        >
    </div>
    @include('admin.mailbox.partials.contact-suggest', ['field' => $field])
</div>
