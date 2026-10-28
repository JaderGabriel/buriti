@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Sistema · Configurações</p>
        <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">E-mail</h1>
        <p class="mt-1 max-w-2xl text-mist">
            SMTP do painel para mensagens operacionais (teste agora). Convites de depoimento <strong class="text-snow">não são enviados por e-mail</strong> — o admin copia o link e envia.
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
            <span class="rounded-sm px-2 py-1 text-[10px] font-semibold uppercase tracking-wide {{ $mail['ready'] ? 'bg-emerald-500/15 text-emerald-300' : 'bg-brand/15 text-brand-bright' }}">
                {{ $mail['mailer'] }}
            </span>
        </div>
        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-mist">Host</dt>
                <dd class="font-mono text-xs text-snow">{{ $mail['host'] ?: '—' }}:{{ $mail['port'] ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-mist">Remetente</dt>
                <dd class="text-snow">{{ $mail['from_name'] ?: '—' }} &lt;{{ $mail['from_address'] ?: '—' }}&gt;</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-mist">Usuário</dt>
                <dd class="font-mono text-xs text-snow">{{ $mail['username'] ?: '—' }}</dd>
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
                <h2 class="font-display text-lg font-semibold">Servidor</h2>
                <p class="mt-1 text-sm text-mist">Valores vazios continuam a usar o <code>.env</code>. Senha em branco não altera a gravada.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-mist">Mailer</span>
                    <select name="mail_mailer" class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow">
                        <option value="smtp" @selected(old('mail_mailer', $form['mail_mailer']) === 'smtp')>SMTP</option>
                        <option value="log" @selected(old('mail_mailer', $form['mail_mailer']) === 'log')>Log (não entrega)</option>
                        <option value="sendmail" @selected(old('mail_mailer', $form['mail_mailer']) === 'sendmail')>Sendmail</option>
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-mist">Criptografia</span>
                    <select name="mail_encryption" class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow">
                        <option value="tls" @selected(old('mail_encryption', $form['mail_encryption']) === 'tls')>TLS (STARTTLS, porta 587)</option>
                        <option value="ssl" @selected(old('mail_encryption', $form['mail_encryption']) === 'ssl')>SSL (SMTPS, porta 465)</option>
                        <option value="none" @selected(old('mail_encryption', $form['mail_encryption']) === 'none')>Nenhuma</option>
                    </select>
                </label>
                <x-ui.input name="mail_host" label="Host" :value="$form['mail_host']" placeholder="smtp.exemplo.com" />
                <x-ui.input name="mail_port" label="Porta" :value="$form['mail_port']" placeholder="587" />
                <x-ui.input name="mail_username" label="Usuário" :value="$form['mail_username']" placeholder="conta@exemplo.com" autocomplete="off" />
                <x-ui.input type="password" name="mail_password" label="Senha" value="" placeholder="{{ $mail['has_password'] ? '•••••••• (deixe em branco para manter)' : 'Opcional' }}" autocomplete="new-password" />
                <x-ui.input type="email" name="mail_from_address" label="Remetente (e-mail)" :value="$form['mail_from_address']" placeholder="nao-responda@exemplo.com" />
                <x-ui.input name="mail_from_name" label="Remetente (nome)" :value="$form['mail_from_name']" placeholder="BURI-TI" />
            </div>
            <button type="submit" class="pm-btn pm-btn--primary">
                <x-ui.icon name="settings" class="h-4 w-4" />
                Salvar e-mail
            </button>
        </form>

        <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="space-y-4 rounded-sm border border-line bg-panel p-5 sm:p-6 h-fit">
            @csrf
            <div>
                <h2 class="font-display text-lg font-semibold">Enviar teste</h2>
                <p class="mt-1 text-sm text-mist">Dispara uma mensagem curta para conferir o SMTP. Não envia convite de depoimento.</p>
            </div>
            <x-ui.input type="email" name="to" label="Destinatário" :value="old('to', $defaultTo)" required placeholder="voce@exemplo.com" />
            <button type="submit" class="pm-btn pm-btn--ghost">
                <x-ui.icon name="mail" class="h-4 w-4" />
                Enviar e-mail de teste
            </button>
        </form>
    </div>
@endsection
