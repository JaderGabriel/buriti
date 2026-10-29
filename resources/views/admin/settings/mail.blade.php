@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Sistema · Configurações</p>
        <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">E-mail</h1>
        <p class="mt-1 max-w-2xl text-mist">
            A conta gravada aqui é a identidade: o mesmo login autentica o SMTP (envio) e o IMAP (caixa).
            O remetente fica preso a essa conta — não é possível fingir ser outro endereço.
            Depois de cada SMTP, a mensagem é copiada para Enviados no mailserver (salvo provedores que já o fazem).
            Convites de depoimento <strong class="text-snow">não são enviados por e-mail</strong> — o admin copia o link.
        </p>
        <p class="mt-3">
            <a href="{{ route('admin.settings.edit') }}" class="text-sm text-brand-bright hover:underline">← Configurações</a>
        </p>
    </div>

    <article class="mb-6 rounded-sm border border-line bg-panel p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-mist">Estado atual</p>
                <h2 class="mt-1 font-display text-lg font-semibold text-snow">{{ $mail['label'] }}</h2>
                <p class="mt-1 text-sm text-mist">{{ $mail['hint'] }}</p>
            </div>
            <span class="rounded-sm px-2 py-1 text-[10px] font-semibold uppercase tracking-wide {{ $mail['ready'] && ($mail['aligned'] ?? true) ? 'bg-emerald-500/15 text-emerald-300' : 'bg-brand/15 text-brand-bright' }}">
                {{ $mail['mailer'] }}
            </span>
        </div>
        @if(!empty($mail['warnings']))
            <ul class="mt-4 space-y-2 rounded-sm border border-amber-400/40 bg-amber-500/10 px-3 py-3 text-sm text-amber-100">
                @foreach($mail['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        @endif
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-mist">SMTP</dt>
                <dd class="font-mono text-xs text-snow">{{ $mail['host'] ?: '—' }}:{{ $mail['port'] ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-mist">Conta</dt>
                <dd class="text-snow">{{ $mail['from_name'] ?: '—' }} &lt;{{ $mail['from_address'] ?: '—' }}&gt;</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-mist">IMAP</dt>
                <dd class="font-mono text-xs text-snow">{{ $mail['imap_host'] ?: '—' }}:{{ $mail['imap_port'] ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-mist">Senha</dt>
                <dd class="text-snow">{{ $mail['has_password'] ? 'Definida' : 'Não definida neste painel' }}</dd>
            </div>
        </dl>
    </article>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]">
        <form method="POST" action="{{ route('admin.settings.mail.update') }}" class="space-y-4 rounded-sm border border-line bg-panel p-5 sm:p-6">
            @csrf
            @method('PUT')
            <div>
                <h2 class="font-display text-lg font-semibold">Servidor e conta</h2>
                <p class="mt-1 text-sm text-mist">Valores vazios continuam a usar o <code>.env</code>. Senha em branco não altera a gravada. O From é sempre o login — Gmail/Outlook só com o SMTP deles (550 5.7.26).</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-mist">Mailer</span>
                    <select name="mail_mailer" class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow">
                        <option value="smtp" @selected(old('mail_mailer', $form['mail_mailer']) === 'smtp')>SMTP autenticado</option>
                        <option value="log" @selected(old('mail_mailer', $form['mail_mailer']) === 'log')>Log (não entrega)</option>
                        <option value="sendmail" @selected(old('mail_mailer', $form['mail_mailer']) === 'sendmail')>Sendmail (sem login — não uses em produção)</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-mist">Criptografia SMTP</span>
                    <select name="mail_encryption" class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow">
                        <option value="tls" @selected(old('mail_encryption', $form['mail_encryption']) === 'tls')>TLS (STARTTLS, porta 587)</option>
                        <option value="ssl" @selected(old('mail_encryption', $form['mail_encryption']) === 'ssl')>SSL (SMTPS, porta 465)</option>
                        <option value="none" @selected(old('mail_encryption', $form['mail_encryption']) === 'none')>Nenhuma</option>
                    </select>
                </label>
                <x-ui.input name="mail_host" label="Host SMTP" :value="$form['mail_host']" placeholder="mail.buriti.dev.br" />
                <x-ui.input name="mail_port" label="Porta SMTP" :value="$form['mail_port']" placeholder="587" />
                <x-ui.input type="email" name="mail_username" label="Conta (login SMTP e IMAP)" :value="$form['mail_username']" placeholder="{{ $mail['suggested_from'] ?? 'contato@buriti.dev.br' }}" autocomplete="off" />
                <x-ui.input type="password" name="mail_password" label="Senha da conta" value="" placeholder="{{ $mail['has_password'] ? '•••••••• (deixe em branco para manter)' : 'Senha ou senha de app' }}" autocomplete="new-password" />
                <x-ui.input name="mail_from_name" label="Nome a apresentar" :value="$form['mail_from_name']" placeholder="BURI-TI" />
                <p class="sm:col-span-2 text-xs text-mist">Remetente = a conta acima. Preferível um endereço do teu domínio com SPF a autorizar o IP deste servidor, ou SMTP do Gmail com senha de app.</p>
            </div>

            <div class="border-t border-line pt-4">
                <h3 class="font-display text-base font-semibold">IMAP (caixa)</h3>
                <p class="mt-1 text-sm text-mist">A mesma conta lê a INBOX e grava os enviados. Vazio usa o host derivado do SMTP (ex. smtp. → imap.) na porta 993 SSL.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="mail_imap_host" label="Host IMAP" :value="$form['mail_imap_host']" placeholder="{{ $mail['imap_host'] ?: 'imap.exemplo.com' }}" />
                    <x-ui.input name="mail_imap_port" label="Porta IMAP" :value="$form['mail_imap_port']" placeholder="993" />
                    <label class="block text-sm">
                        <span class="text-mist">Criptografia IMAP</span>
                        <select name="mail_imap_encryption" class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow">
                            <option value="ssl" @selected(old('mail_imap_encryption', $form['mail_imap_encryption']) === 'ssl')>SSL (993)</option>
                            <option value="tls" @selected(old('mail_imap_encryption', $form['mail_imap_encryption']) === 'tls')>STARTTLS (143)</option>
                            <option value="none" @selected(old('mail_imap_encryption', $form['mail_imap_encryption']) === 'none')>Nenhuma</option>
                        </select>
                    </label>
                    <x-ui.input name="mail_imap_sent_folder" label="Pasta Enviados" :value="$form['mail_imap_sent_folder']" placeholder="Sent (auto se vazio)" />
                </div>
            </div>

            <button type="submit" class="pm-btn pm-btn--primary">
                <x-ui.icon name="settings" class="h-4 w-4" />
                Salvar e-mail
            </button>
        </form>

        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="space-y-4 rounded-sm border border-line bg-panel p-5 sm:p-6">
                @csrf
                <div>
                    <h2 class="font-display text-lg font-semibold">Enviar teste</h2>
                    <p class="mt-1 text-sm text-mist">Sai autenticado como a conta. A cópia deve aparecer em Enviados no mailserver. Não envia convite de depoimento.</p>
                </div>
                <x-ui.input type="email" name="to" label="Destinatário" :value="old('to', $defaultTo)" required placeholder="voce@exemplo.com" />
                <button type="submit" class="pm-btn pm-btn--ghost">
                    <x-ui.icon name="mail" class="h-4 w-4" />
                    Enviar e-mail de teste
                </button>
            </form>

            <form method="POST" action="{{ route('admin.settings.mail.inbox') }}" class="space-y-4 rounded-sm border border-line bg-panel p-5 sm:p-6">
                @csrf
                <div>
                    <h2 class="font-display text-lg font-semibold">Caixa (IMAP)</h2>
                    <p class="mt-1 text-sm text-mist">Lê as mensagens recentes da INBOX com o mesmo login. Confirma o recebimento no mailserver.</p>
                </div>
                <button type="submit" class="pm-btn pm-btn--ghost">
                    <x-ui.icon name="mail" class="h-4 w-4" />
                    Abrir caixa
                </button>
                @if(session()->has('mail_inbox'))
                    @php $inbox = session('mail_inbox'); @endphp
                    @if($inbox === [])
                        <p class="text-sm text-mist">Nenhuma mensagem na INBOX.</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach($inbox as $item)
                                <li class="rounded-sm border border-line bg-ink px-3 py-2">
                                    <p class="text-snow">{{ $item['subject'] !== '' ? $item['subject'] : '(sem assunto)' }}</p>
                                    <p class="mt-0.5 text-xs text-mist">{{ $item['from'] }} · {{ $item['date'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </form>
        </div>
    </div>
@endsection
