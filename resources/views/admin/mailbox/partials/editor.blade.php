<div class="mail-editor">
    <div class="mail-editor__toolbar" role="toolbar" aria-label="Formatação">
        <button type="button" class="mail-editor__btn" title="Negrito" @mousedown.prevent="format('bold')">
            <x-ui.icon name="bold" class="h-4 w-4" />
        </button>
        <button type="button" class="mail-editor__btn" title="Itálico" @mousedown.prevent="format('italic')">
            <x-ui.icon name="italic" class="h-4 w-4" />
        </button>
        <button type="button" class="mail-editor__btn" title="Sublinhado" @mousedown.prevent="format('underline')">
            <x-ui.icon name="underline" class="h-4 w-4" />
        </button>
        <button type="button" class="mail-editor__btn" title="Tachado" @mousedown.prevent="format('strikeThrough')">
            <x-ui.icon name="strike" class="h-4 w-4" />
        </button>
        <span class="mail-editor__sep" aria-hidden="true"></span>
        <button type="button" class="mail-editor__btn" title="Lista" @mousedown.prevent="format('insertUnorderedList')">
            <x-ui.icon name="list" class="h-4 w-4" />
        </button>
        <button type="button" class="mail-editor__btn" title="Lista numerada" @mousedown.prevent="format('insertOrderedList')">
            <x-ui.icon name="list-ol" class="h-4 w-4" />
        </button>
        <button type="button" class="mail-editor__btn" title="Citação" @mousedown.prevent="format('formatBlock', 'blockquote')">
            <x-ui.icon name="quote" class="h-4 w-4" />
        </button>
        <button type="button" class="mail-editor__btn" title="Link" @mousedown.prevent="insertLink()">
            <x-ui.icon name="link" class="h-4 w-4" />
        </button>
        <span class="mail-editor__sep" aria-hidden="true"></span>
        <button type="button" class="mail-editor__btn" title="Imagem no texto" @mousedown.prevent="$refs.inlineInput.click()">
            <x-ui.icon name="image" class="h-4 w-4" />
        </button>
        <button type="button" class="mail-editor__btn" title="Anexar arquivo" @mousedown.prevent="$refs.attachPicker.click()">
            <x-ui.icon name="paperclip" class="h-4 w-4" />
            <span class="mail-editor__badge" x-show="items.length > 0" x-cloak x-text="items.length"></span>
        </button>
        <button type="button" class="mail-editor__btn" title="Limpar formatação" @mousedown.prevent="format('removeFormat')">
            <x-ui.icon name="eraser" class="h-4 w-4" />
        </button>
    </div>
    <div
        class="mail-editor__canvas"
        :class="{ 'is-empty': editorEmpty }"
        contenteditable="true"
        role="textbox"
        aria-label="Corpo da mensagem"
        data-placeholder="Escreva a mensagem…"
        x-ref="editor"
        @input="syncEditor()"
        @paste="onPaste($event)"
        @dragover.prevent
        @drop.prevent="onDrop($event)"
        @keydown.ctrl.b.prevent="format('bold')"
        @keydown.meta.b.prevent="format('bold')"
        @keydown.ctrl.i.prevent="format('italic')"
        @keydown.meta.i.prevent="format('italic')"
        @keydown.ctrl.u.prevent="format('underline')"
        @keydown.meta.u.prevent="format('underline')"
    ></div>
    <textarea name="body" id="mail-body" class="sr-only" x-ref="bodyField">{{ old('body', $compose['body'] ?? '') }}</textarea>
</div>
@php
    $quoteHtml = \App\Support\MailHtml::sanitize((string) old('quote', $compose['quote'] ?? ''));
    $quoteLabel = $compose['quote_label'] ?? 'Mensagem original';
@endphp
@if($quoteHtml !== '')
    <details class="mail-quote-pane" open>
        <summary>{{ $quoteLabel }}</summary>
        <div class="mail-quote-pane__body">{!! $quoteHtml !!}</div>
    </details>
    <textarea name="quote" class="sr-only" x-ref="quoteField">{{ $quoteHtml }}</textarea>
@endif
<div class="mail-compose__attach">
    <button type="button" class="mail-compose__attach-btn" @click="$refs.attachPicker.click()">
        <x-ui.icon name="paperclip" class="h-4 w-4" />
        Anexos
        <span class="mail-attach-count" x-show="items.length > 0" x-cloak>
            <span x-text="items.length"></span>
            · <span x-text="sizeLabel"></span>
        </span>
    </button>
    <p class="mail-compose__attach-hint">Até 5 arquivos · 10 MB no total. Imagens podem ir no texto.</p>
    <template x-if="loadingCount > 0">
        <p class="mail-compose__attach-hint" data-mail-attach-loading>Carregando <span x-text="loadingCount"></span>…</p>
    </template>
    <div class="mail-compose__file-sink" hidden aria-hidden="true">
        <input
            x-ref="attachPicker"
            type="file"
            multiple
            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.jpg,.jpeg,.png,.webp,.gif,.odt,.ods"
            @change="addFiles($event.target.files, false); $event.target.value = ''"
        >
        <input
            x-ref="inlineInput"
            type="file"
            accept=".jpg,.jpeg,.png,.webp,.gif"
            @change="addFiles($event.target.files, true); $event.target.value = ''"
        >
        <input x-ref="attachInput" type="file" name="attachments[]" multiple>
        <div x-ref="inlineMount"></div>
    </div>
    @error('attachments')
        <p class="mail-compose__error">{{ $message }}</p>
    @enderror
    @error('attachments.0')
        <p class="mail-compose__error">{{ $message }}</p>
    @enderror
    @error('body')
        <p class="mail-compose__error">{{ $message }}</p>
    @enderror
    @error('inline')
        <p class="mail-compose__error">{{ $message }}</p>
    @enderror
    <p class="mail-compose__error" x-show="attachError" x-cloak x-text="attachError"></p>
</div>
<template x-if="items.length > 0">
    <div class="mail-gallery">
        <div class="mail-gallery__progress" x-show="loadingCount > 0">
            <span class="mail-gallery__progress-bar"></span>
        </div>
        <template x-for="item in items" :key="item.id">
            <article class="mail-gallery__card" :class="{ 'is-loading': item.loading, 'is-inline': item.inline }">
                <div class="mail-gallery__thumb">
                    <img x-show="item.preview" :src="item.preview" :alt="item.name" class="mail-gallery__img">
                    <x-ui.icon name="paperclip" class="h-5 w-5" x-show="! item.preview" />
                    <span class="mail-gallery__spinner" x-show="item.loading" aria-hidden="true"></span>
                </div>
                <div class="mail-gallery__meta">
                    <p class="mail-gallery__name" x-text="item.name"></p>
                    <p class="mail-gallery__size">
                        <span x-text="formatSize(item.size)"></span>
                        <span x-show="item.inline"> · no texto</span>
                        <span x-show="item.loading"> · carregando</span>
                    </p>
                </div>
                <button type="button" class="mail-gallery__remove" @click="removeItem(item.id)" title="Remover anexo">×</button>
            </article>
        </template>
    </div>
</template>
