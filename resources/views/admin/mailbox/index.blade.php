@extends('layouts.admin')

@section('content')
    @php
        $ready = (bool) ($workspace['ready'] ?? false);
        $folders = $workspace['folders'] ?? [];
        $folder = $workspace['folder'] ?? 'INBOX';
        $listing = $workspace['listing'] ?? ['messages' => [], 'exists' => 0, 'page' => 1, 'pages' => 1];
        $selected = $workspace['selected'] ?? null;
        $identity = $workspace['identity'] ?? ['address' => '', 'name' => '', 'signature' => ''];
        $error = $workspace['error'] ?? null;
        $isOpen = $selected !== null || $mode === 'compose';
        $messages = $listing['messages'] ?? [];
        $folderMeta = collect($folders)->firstWhere('name', $folder) ?? ['label' => $folder, 'role' => 'other', 'unseen' => 0, 'messages' => $listing['exists'] ?? 0];
        $isSent = ($folderMeta['role'] ?? '') === 'sent';
        $inboxUnseen = (int) (collect($folders)->firstWhere('role', 'inbox')['unseen'] ?? 0);
        $composeMode = $compose['modo'] ?? 'new';
        $composeTitle = match ($composeMode) {
            'reply' => 'Responder',
            'reply-all' => 'Responder a todos',
            'forward' => 'Encaminhar',
            'contact-reply' => 'Resposta ao site',
            default => 'Nova mensagem',
        };
        $folderIcon = fn (string $role) => match ($role) {
            'inbox' => 'mail',
            'sent' => 'delivery',
            'drafts' => 'pencil',
            'trash' => 'trash',
            'junk' => 'lost',
            'archive' => 'folder',
            default => 'folder',
        };
        $showCc = filled(old('cc', $compose['cc'] ?? ''));
        $showBcc = filled(old('bcc', $compose['bcc'] ?? ''));
        $mailList = function (array $extra = []) use ($folder, $query): array {
            return array_filter([
                'pasta' => $folder,
                'buscar' => $query !== '' ? $query : null,
                ...$extra,
            ], fn ($value) => $value !== null && $value !== '');
        };
    @endphp

    <div class="messenger-shell mail-shell">
        @if($error)
            <x-ui.flash :message="$error" type="error" />
        @endif

        @if(! $ready && $mode !== 'compose')
            <article class="mail-setup">
                <p class="mail-kicker">Comercial · Correio</p>
                <h1 class="mt-2 font-display text-2xl font-bold">Correio da conta</h1>
                <p class="mt-3 text-sm text-mist">
                    Liga SMTP e IMAP em Sistema → E-mail. A mesma identidade serve para o painel, as respostas do site e o bot no Telegram
                    (<code>/email</code> e <code>/mensagem responder</code>).
                </p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('admin.settings.mail') }}" class="pm-btn pm-btn--primary">
                        <x-ui.icon name="settings" class="h-4 w-4" />
                        Configurar conta
                    </a>
                    <a href="{{ route('admin.mailbox.compose') }}" class="pm-btn pm-btn--ghost">
                        <x-ui.icon name="pencil" class="h-4 w-4" />
                        Escrever mesmo assim
                    </a>
                </div>
            </article>
        @else
            <div @class(['messenger', 'mail', 'messenger--open' => $isOpen])>
                <aside class="mail-folders" aria-label="Pastas">
                    <div class="mail-folders__head">
                        <p class="mail-kicker">Correio</p>
                        <p class="mt-1 truncate text-sm font-semibold text-snow" title="{{ $identity['address'] }}">{{ $identity['name'] ?: $identity['address'] ?: 'Conta' }}</p>
                        <p class="truncate text-[11px] text-mist" title="{{ $identity['address'] }}">{{ $identity['address'] ?: 'sem identidade' }}</p>
                    </div>
                    <nav class="mail-folders__nav">
                        @foreach($folders as $item)
                            <a
                                href="{{ route('admin.mailbox.index', ['pasta' => $item['name']]) }}"
                                @class(['mail-folder', 'is-active' => $folder === $item['name']])
                            >
                                <span class="mail-folder__label">
                                    <x-ui.icon :name="$folderIcon($item['role'] ?? 'other')" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                                    <span class="truncate">{{ $item['label'] }}</span>
                                </span>
                                @if(($item['unseen'] ?? 0) > 0)
                                    <span class="messenger-badge">{{ $item['unseen'] }}</span>
                                @elseif(($item['messages'] ?? 0) > 0)
                                    <span class="mail-folder__count">{{ $item['messages'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                    <form method="POST" action="{{ route('admin.mailbox.folders.store') }}" class="mail-folders__create">
                        @csrf
                        <input type="text" name="name" placeholder="Nova pasta" maxlength="80" required class="mail-input" autocomplete="off">
                        <button type="submit" class="messenger-icon-btn" title="Criar pasta">
                            <x-ui.icon name="plus" class="h-4 w-4" />
                        </button>
                    </form>
                    <a href="{{ route('admin.settings.mail') }}" class="mail-folders__settings">
                        <x-ui.icon name="settings" class="h-3.5 w-3.5" />
                        Conta SMTP/IMAP
                    </a>
                </aside>

                <aside class="messenger-list" aria-label="Mensagens">
                    <header class="messenger-list__header">
                        <div class="min-w-0">
                            <p class="mail-kicker">{{ $folderMeta['label'] ?? $folder }}</p>
                            <h1 class="mt-1 font-display text-xl font-bold text-snow">
                                {{ ($listing['exists'] ?? 0) }} mensagem{{ ($listing['exists'] ?? 0) === 1 ? '' : 's' }}
                                @if(($folderMeta['unseen'] ?? 0) > 0)
                                    <span class="messenger-badge">{{ $folderMeta['unseen'] }} nova{{ $folderMeta['unseen'] === 1 ? '' : 's' }}</span>
                                @endif
                            </h1>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <a href="{{ route('admin.mailbox.index', $mailList()) }}" class="messenger-icon-btn" title="Atualizar">
                                <x-ui.icon name="restore" class="h-4 w-4" />
                            </a>
                            <a href="{{ route('admin.mailbox.compose') }}" class="pm-btn pm-btn--primary" title="Escrever">
                                <x-ui.icon name="pencil" class="h-4 w-4" />
                                Escrever
                            </a>
                        </div>
                    </header>
                    <form
                        method="GET"
                        action="{{ route('admin.mailbox.index') }}"
                        class="mail-search"
                        role="search"
                        data-mail-search
                        aria-label="Procurar nesta pasta"
                    >
                        <input type="hidden" name="pasta" value="{{ $folder }}">
                        <label class="sr-only" for="mail-folder-search">Procurar nesta pasta</label>
                        <span class="mail-search__icon" aria-hidden="true">
                            <x-ui.icon name="search" class="h-4 w-4" />
                        </span>
                        <input
                            id="mail-folder-search"
                            type="text"
                            name="buscar"
                            value="{{ $query }}"
                            placeholder="Remetente, destinatário ou assunto"
                            class="mail-input mail-search__input"
                            autocomplete="off"
                            autocorrect="off"
                            spellcheck="false"
                            enterkeyhint="search"
                            data-mail-search-input
                        >
                        <a
                            href="{{ route('admin.mailbox.index', ['pasta' => $folder]) }}"
                            @class(['mail-search__clear', 'is-idle' => $query === ''])
                            title="Mostrar todas as mensagens"
                            aria-label="Limpar busca desta pasta"
                            data-mail-search-clear
                        >
                            <x-ui.icon name="lost" class="h-3.5 w-3.5" />
                        </a>
                    </form>
                    <div class="messenger-list__scroll">
                        @forelse($messages as $item)
                            @php
                                $peer = $isSent ? ($item['to'] ?: $item['from']) : ($item['from'] ?: $item['to']);
                            @endphp
                            <a
                                href="{{ route('admin.mailbox.index', $mailList(['uid' => $item['uid'], 'page' => $listing['page'] ?? 1])) }}"
                                @class([
                                    'messenger-item',
                                    'messenger-item--active' => ($selected['uid'] ?? null) === $item['uid'],
                                    'messenger-item--unread' => empty($item['seen']),
                                ])
                            >
                                <span class="messenger-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($peer ?: '?', 0, 1)) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span class="truncate font-semibold text-snow">{{ $peer ?: '(sem remetente)' }}</span>
                                        <span class="shrink-0 text-[11px] text-mist">{{ \App\Support\MailDate::compact($item['date'] ?? '') }}</span>
                                    </span>
                                    <span class="mt-0.5 flex items-center gap-1.5">
                                        @if(! empty($item['answered']))
                                            <x-ui.icon name="restore" class="h-3 w-3 shrink-0 text-brand-bright" />
                                        @endif
                                        @if(! empty($item['flagged']))
                                            <x-ui.icon name="star" class="h-3 w-3 shrink-0 text-brand-bright" />
                                        @endif
                                        <span class="truncate text-xs {{ empty($item['seen']) ? 'text-snow' : 'text-mist' }}">{{ ($item['subject'] ?? '') !== '' ? $item['subject'] : '(sem assunto)' }}</span>
                                    </span>
                                </span>
                                @if(empty($item['seen']))
                                    <span class="messenger-dot" title="Não lida"></span>
                                @endif
                            </a>
                        @empty
                            <div class="px-5 py-16 text-center text-sm text-mist">
                                @if($query !== '')
                                    <p>Nada encontrado para “{{ $query }}”.</p>
                                    <a href="{{ route('admin.mailbox.index', ['pasta' => $folder]) }}" class="mt-3 inline-block text-brand-bright hover:text-snow">Mostrar todas as mensagens</a>
                                @else
                                    Nenhuma mensagem nesta pasta.
                                @endif
                            </div>
                        @endforelse
                    </div>
                    @if(($listing['pages'] ?? 1) > 1)
                        <div class="messenger-list__footer flex items-center justify-between text-xs text-mist">
                            <a href="{{ route('admin.mailbox.index', $mailList(['page' => max(1, ($listing['page'] ?? 1) - 1)])) }}" class="{{ ($listing['page'] ?? 1) <= 1 ? 'pointer-events-none opacity-40' : 'hover:text-snow' }}">← Anterior</a>
                            <span>{{ $listing['page'] }} / {{ $listing['pages'] }}</span>
                            <a href="{{ route('admin.mailbox.index', $mailList(['page' => min($listing['pages'], ($listing['page'] ?? 1) + 1)])) }}" class="{{ ($listing['page'] ?? 1) >= ($listing['pages'] ?? 1) ? 'pointer-events-none opacity-40' : 'hover:text-snow' }}">Seguinte →</a>
                        </div>
                    @endif
                </aside>

                <section class="messenger-chat" aria-label="Leitura">
                    @if($mode === 'compose')
                        <header class="messenger-chat__header">
                            <div class="min-w-0">
                                <p class="mail-kicker">{{ $composeTitle }}</p>
                                <p class="truncate font-semibold text-snow">De {{ $identity['name'] ?: $identity['address'] ?: 'a conta gravada' }}</p>
                            </div>
                            <a href="{{ route('admin.mailbox.index', ['pasta' => $folder]) }}" class="messenger-icon-btn" title="Fechar">
                                <x-ui.icon name="lost" class="h-4 w-4" />
                            </a>
                        </header>
                        <form
                            method="POST"
                            action="{{ route('admin.mailbox.send') }}"
                            class="mail-compose"
                            x-data="{ cc: {{ $showCc ? 'true' : 'false' }}, bcc: {{ $showBcc ? 'true' : 'false' }} }"
                        >
                            @csrf
                            <input type="hidden" name="in_reply_to" value="{{ $compose['in_reply_to'] ?? '' }}">
                            <input type="hidden" name="reply_folder" value="{{ $compose['reply_folder'] ?? '' }}">
                            <input type="hidden" name="reply_uid" value="{{ $compose['reply_uid'] ?? '' }}">
                            <input type="hidden" name="contact_message_id" value="{{ $compose['contact_message_id'] ?? '' }}">
                            <div class="mail-compose__row">
                                <label for="mail-to">Para</label>
                                <div class="mail-compose__field">
                                    <input id="mail-to" type="text" name="to" value="{{ old('to', $compose['to'] ?? '') }}" required placeholder="destinatario@exemplo.com" class="mail-input" autocomplete="email">
                                    <div class="mail-compose__toggles">
                                        <button type="button" class="mail-compose__toggle" @click="cc = !cc" :class="{ 'is-on': cc }">Cc</button>
                                        <button type="button" class="mail-compose__toggle" @click="bcc = !bcc" :class="{ 'is-on': bcc }">Cco</button>
                                    </div>
                                </div>
                            </div>
                            <div class="mail-compose__row" x-show="cc" x-cloak>
                                <label for="mail-cc">Cc</label>
                                <input id="mail-cc" type="text" name="cc" value="{{ old('cc', $compose['cc'] ?? '') }}" placeholder="cópia" class="mail-input" autocomplete="off">
                            </div>
                            <div class="mail-compose__row" x-show="bcc" x-cloak>
                                <label for="mail-bcc">Cco</label>
                                <input id="mail-bcc" type="text" name="bcc" value="{{ old('bcc', $compose['bcc'] ?? '') }}" placeholder="cópia oculta" class="mail-input" autocomplete="off">
                            </div>
                            <div class="mail-compose__row">
                                <label for="mail-subject">Assunto</label>
                                <input id="mail-subject" type="text" name="subject" value="{{ old('subject', $compose['subject'] ?? '') }}" required class="mail-input">
                            </div>
                            <textarea name="body" required rows="12" class="mail-input mail-compose__body" placeholder="Escreve a mensagem…">{{ old('body', $compose['body'] ?? '') }}</textarea>
                            <div class="mail-compose__bar">
                                <label class="mail-compose__sign">
                                    <input type="hidden" name="signature" value="0">
                                    <input type="checkbox" name="signature" value="1" @checked(old('signature', '1') === '1')>
                                    Assinatura
                                </label>
                                <div class="messenger-composer__actions">
                                    <button type="submit" name="draft" value="1" class="messenger-composer__secondary">Rascunho</button>
                                    <button type="submit" class="messenger-composer__primary">Enviar</button>
                                </div>
                            </div>
                        </form>
                        <details class="mail-signature" open>
                            <summary>Assinatura da conta</summary>
                            <p class="mt-2 text-xs text-mist">Bloco moderno com nome e BURI-TI. O aviso de confidencialidade e LGPD entra sempre no rodapé, mesmo sem este texto.</p>
                            <form method="POST" action="{{ route('admin.mailbox.signature') }}" class="mt-3 grid gap-2">
                                @csrf
                                @method('PUT')
                                <textarea name="mail_signature" rows="5" class="mail-input" placeholder="Nome&#10;BURI-TI · consultoria e software&#10;{{ $identity['address'] }}">{{ old('mail_signature', $identity['signature']) }}</textarea>
                                <button type="submit" class="messenger-composer__secondary justify-self-start">Guardar assinatura</button>
                            </form>
                        </details>
                    @elseif($selected)
                        <header class="messenger-chat__header">
                            <div class="flex min-w-0 items-center gap-3">
                                <a href="{{ route('admin.mailbox.index', ['pasta' => $folder]) }}" class="messenger-back lg:hidden" aria-label="Voltar">←</a>
                                <span class="messenger-avatar messenger-avatar--lg">{{ mb_strtoupper(mb_substr($selected['from'] ?: '?', 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-snow">{{ $selected['subject'] }}</p>
                                    <p class="truncate text-xs text-mist">{{ $selected['from'] }}</p>
                                </div>
                            </div>
                            <div class="mail-read-actions">
                                <a href="{{ route('admin.mailbox.compose', ['modo' => 'reply', 'responder_pasta' => $folder, 'responder_uid' => $selected['uid']]) }}" class="mail-action" title="Responder">
                                    <x-ui.icon name="restore" class="h-4 w-4" />
                                    <span>Responder</span>
                                </a>
                                <a href="{{ route('admin.mailbox.compose', ['modo' => 'reply-all', 'responder_pasta' => $folder, 'responder_uid' => $selected['uid']]) }}" class="mail-action" title="Responder a todos">
                                    <x-ui.icon name="users" class="h-4 w-4" />
                                    <span>Todos</span>
                                </a>
                                <a href="{{ route('admin.mailbox.compose', ['modo' => 'forward', 'responder_pasta' => $folder, 'responder_uid' => $selected['uid']]) }}" class="mail-action" title="Encaminhar">
                                    <x-ui.icon name="delivery" class="h-4 w-4" />
                                    <span>Encaminhar</span>
                                </a>
                                <form method="POST" action="{{ route('admin.mailbox.seen') }}">
                                    @csrf
                                    <input type="hidden" name="pasta" value="{{ $folder }}">
                                    <input type="hidden" name="uid" value="{{ $selected['uid'] }}">
                                    <input type="hidden" name="seen" value="{{ $selected['seen'] ? 0 : 1 }}">
                                    <button type="submit" class="messenger-icon-btn" title="{{ $selected['seen'] ? 'Marcar não lida' : 'Marcar lida' }}">
                                        <x-ui.icon :name="$selected['seen'] ? 'eye-off' : 'eye'" class="h-4 w-4" />
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.mailbox.destroy') }}" onsubmit="return confirm('Enviar para o Lixo?')">
                                    @csrf
                                    <input type="hidden" name="pasta" value="{{ $folder }}">
                                    <input type="hidden" name="uid" value="{{ $selected['uid'] }}">
                                    <button type="submit" class="messenger-icon-btn messenger-icon-btn--danger" title="Apagar">
                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </header>
                        <div class="messenger-chat__thread mail-thread">
                            <dl class="mail-meta">
                                <div><dt>De</dt><dd>{{ $selected['from'] ?: '—' }}</dd></div>
                                <div><dt>Para</dt><dd>{{ $selected['to'] ?: '—' }}</dd></div>
                                @if($selected['cc'])
                                    <div><dt>Cc</dt><dd>{{ $selected['cc'] }}</dd></div>
                                @endif
                                <div><dt>Data</dt><dd>{{ \App\Support\MailDate::full($selected['date'] ?? '') }}</dd></div>
                            </dl>
                            @if($selected['html'])
                                <iframe class="mail-html" sandbox srcdoc="{{ $selected['html'] }}" title="Corpo da mensagem"></iframe>
                            @else
                                <div class="mail-text">{{ $selected['text'] !== '' ? $selected['text'] : '(sem conteúdo)' }}</div>
                            @endif
                            @if($selected['attachments'] !== [])
                                <ul class="mail-attachments">
                                    @foreach($selected['attachments'] as $file)
                                        <li>
                                            <a href="{{ route('admin.mailbox.attachment', ['pasta' => $folder, 'uid' => $selected['uid'], 'i' => $file['index']]) }}" class="mail-chip">
                                                <x-ui.icon name="download" class="h-3.5 w-3.5" />
                                                {{ $file['name'] }}
                                                <span>{{ number_format($file['size'] / 1024, 1) }} KB</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if(count($folders) > 1)
                                <form method="POST" action="{{ route('admin.mailbox.move') }}" class="mail-move">
                                    @csrf
                                    <input type="hidden" name="pasta" value="{{ $folder }}">
                                    <input type="hidden" name="uid" value="{{ $selected['uid'] }}">
                                    <label class="text-xs text-mist">Mover para
                                        <select name="destino" class="mail-input mt-1">
                                            @foreach($folders as $item)
                                                @if($item['name'] !== $folder)
                                                    <option value="{{ $item['name'] }}">{{ $item['label'] }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="submit" class="messenger-composer__secondary mt-2">Mover</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <div class="messenger-empty">
                            <div class="messenger-empty__card">
                                <span class="messenger-avatar messenger-avatar--xl"><x-ui.icon name="mail" class="h-7 w-7" /></span>
                                <h2 class="mt-4 font-display text-lg font-semibold">Correio da conta</h2>
                                <p class="mt-2 max-w-sm text-sm text-mist">
                                    Escolhe uma mensagem à esquerda ou escreve uma nova. O envio sai autenticado como {{ $identity['address'] ?: 'a conta gravada' }}{{ $inboxUnseen > 0 ? ' · '.$inboxUnseen.' por ler na caixa de entrada' : '' }}.
                                </p>
                                <div class="mt-5 flex flex-wrap justify-center gap-2">
                                    <a href="{{ route('admin.mailbox.compose') }}" class="pm-btn pm-btn--primary">
                                        <x-ui.icon name="pencil" class="h-4 w-4" />
                                        Escrever
                                    </a>
                                    <a href="{{ route('admin.messages.index') }}" class="pm-btn pm-btn--ghost">
                                        Mensagens do site
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                </section>
            </div>
        @endif
    </div>
@endsection
