@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Ferramentas</p>
        <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">Integrações</h1>
        <p class="mt-1 max-w-2xl text-mist">
            Trello, Notion, Telegram e Google ligados ao fluxo do site — CRM, tarefas, projetos e contato.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Trello', $trello, 'Kanban de entregas', 2],
            ['Notion', $notion, 'Docs e briefs', 2],
            ['Telegram', $telegram, 'Bot CRM + inbox', 3],
            ['Google', $google, 'Agenda e Meet', 3],
        ] as [$name, $status, $hint, $maxLevel])
            <article class="rounded-sm border border-line bg-panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-mist">{{ $hint }}</p>
                        <h2 class="mt-1 font-display text-lg font-semibold text-snow">{{ $name }}</h2>
                    </div>
                    <span class="rounded-sm bg-brand/15 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-brand-bright">
                        Nível {{ $status['level'] }}/{{ $maxLevel }}
                    </span>
                </div>
                <p class="mt-3 text-sm font-medium text-snow">{{ $status['label'] }}</p>
                <p class="mt-1 text-sm text-mist">{{ $status['next_step'] }}</p>
            </article>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.integrations.update') }}" class="mt-8 space-y-8">
        @csrf
        @method('PUT')

        <section id="trello" class="scroll-mt-6 rounded-sm border border-line bg-panel p-5 sm:p-6">
            <div class="integration-section-layout">
                <div>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-xl font-semibold">Trello</h2>
                            <p class="mt-1 text-sm text-mist">Board de cartões para oportunidades e entregas BURI-TI.</p>
                        </div>
                        @if(! empty($trello['board_url']))
                            <a href="{{ $trello['board_url'] }}" target="_blank" rel="noopener" class="text-sm font-semibold text-brand-bright hover:underline">Abrir board →</a>
                        @endif
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="trello_board_id" label="Board ID" :value="$settings['trello_board_id'] ?? ''" placeholder="ex.: 64f1a2b3c4d5e6f7" />
                        <x-ui.input type="url" name="trello_board_url" label="URL do board" :value="$settings['trello_board_url'] ?? ''" placeholder="https://trello.com/b/..." />
                        <x-ui.input name="trello_list_todo_id" label="List ID (To do / entrada)" :value="$settings['trello_list_todo_id'] ?? ''" placeholder="Opcional — lista padrão de entrada" class="sm:col-span-2" />
                    </div>

                    <div class="mt-4 rounded-sm border border-line bg-ink/30 px-4 py-3 text-xs text-mist">
                        <p class="font-semibold text-snow">Credenciais (.env)</p>
                        <p class="mt-1">
                            API pronta:
                            <span class="{{ $trello['api_token_set'] ? 'text-brand-bright' : 'text-mist' }}">
                                {{ $trello['api_token_set'] ? 'sim' : 'não — defina TRELLO_API_KEY e TRELLO_TOKEN' }}
                            </span>
                        </p>
                    </div>
                </div>

                <x-admin.inline-docs title="Como ligar o Trello">
                    <ol>
                        <li>Abra o board no Trello e copie a URL (<code>https://trello.com/b/...</code>).</li>
                        <li>O <strong>Board ID</strong> está na URL ou em “Mais → Imprimir e exportar → Exportar JSON”.</li>
                        <li>Opcional: abra a lista “A fazer”, copie o ID da lista (via URL ou API) para <strong>List ID</strong>.</li>
                        <li>Em <a href="https://trello.com/power-ups/admin" target="_blank" rel="noopener">Power-Ups / API Key</a>, gere <code>TRELLO_API_KEY</code> e <code>TRELLO_TOKEN</code> no <code>.env</code>.</li>
                        <li>Salve aqui e rode <code>php artisan config:clear</code> se alterou o <code>.env</code>.</li>
                    </ol>
                </x-admin.inline-docs>
            </div>
        </section>

        <section id="notion" class="scroll-mt-6 rounded-sm border border-line bg-panel p-5 sm:p-6">
            <div class="integration-section-layout">
                <div>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-xl font-semibold">Notion</h2>
                            <p class="mt-1 text-sm text-mist">Base de conhecimento, briefs e propostas.</p>
                        </div>
                        @if(! empty($notion['workspace_url']))
                            <a href="{{ $notion['workspace_url'] }}" target="_blank" rel="noopener" class="text-sm font-semibold text-brand-bright hover:underline">Abrir workspace →</a>
                        @endif
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="notion_database_id" label="Database ID" :value="$settings['notion_database_id'] ?? ''" placeholder="UUID da base" />
                        <x-ui.input type="url" name="notion_workspace_url" label="URL do workspace" :value="$settings['notion_workspace_url'] ?? ''" placeholder="https://www.notion.so/..." />
                        <div class="sm:col-span-2">
                            <x-ui.input type="url" name="notion_default_page_url" label="Página padrão (roadmap / playbook)" :value="$settings['notion_default_page_url'] ?? ''" placeholder="https://www.notion.so/..." />
                        </div>
                    </div>

                    <div class="mt-4 rounded-sm border border-line bg-ink/30 px-4 py-3 text-xs text-mist">
                        <p class="font-semibold text-snow">Credenciais (.env)</p>
                        <p class="mt-1">
                            Token pronto:
                            <span class="{{ $notion['token_set'] ? 'text-brand-bright' : 'text-mist' }}">
                                {{ $notion['token_set'] ? 'sim' : 'não — defina NOTION_TOKEN' }}
                            </span>
                        </p>
                    </div>
                </div>

                <x-admin.inline-docs title="Como ligar o Notion">
                    <ol>
                        <li>Crie uma integração em <a href="https://www.notion.so/my-integrations" target="_blank" rel="noopener">Notion → My integrations</a>.</li>
                        <li>Copie o <strong>Internal Integration Token</strong> para <code>NOTION_TOKEN</code> no <code>.env</code>.</li>
                        <li>Na base desejada: ··· → Connections → ligue a integração.</li>
                        <li>O <strong>Database ID</strong> é o UUID na URL da base (32 caracteres).</li>
                        <li>Preencha workspace/página padrão para atalhos no painel e salve.</li>
                    </ol>
                </x-admin.inline-docs>
            </div>
        </section>

        <section id="telegram" class="scroll-mt-6 rounded-sm border border-line bg-panel p-5 sm:p-6">
            <div class="integration-section-layout">
                <div>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-xl font-semibold">Telegram Bot</h2>
                            <p class="mt-1 text-sm text-mist">
                                Comandos no app para CRM e notificações do formulário + lembretes de tarefas.
                            </p>
                        </div>
                        <a href="https://t.me/BotFather" target="_blank" rel="noopener" class="text-sm font-semibold text-brand-bright hover:underline">Abrir BotFather →</a>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-ui.input
                            name="telegram_notify_chat_id"
                            label="Chat extra para notificações do site (opcional)"
                            :value="$settings['telegram_notify_chat_id'] ?? ''"
                            placeholder="Chat ID — além dos admins logados no bot"
                            class="sm:col-span-2"
                        />
                    </div>

                    <div class="mt-4 rounded-sm border border-line bg-ink/20 px-4 py-3 text-sm text-mist">
                        <p class="font-semibold text-snow">Admins com sessão no bot</p>
                        @forelse($telegramAdmins as $adminUser)
                            <p class="mt-1">{{ $adminUser->name }} · {{ $adminUser->email }} · <code class="text-snow">{{ $adminUser->telegram_chat_id }}</code></p>
                        @empty
                            <p class="mt-1">Nenhum admin logado. No Telegram: <code class="text-snow">/login email | senha</code></p>
                        @endforelse
                    </div>

                    <div class="mt-4 space-y-2 rounded-sm border border-line bg-ink/30 px-4 py-3 text-xs text-mist">
                        <p class="font-semibold text-snow">Estado</p>
                        <p>
                            Token:
                            <span class="{{ $telegram['token_set'] ? 'text-brand-bright' : 'text-mist' }}">
                                {{ $telegram['token_set'] ? 'sim' : 'não' }}
                            </span>
                            · Webhook secret:
                            <span class="{{ $telegram['webhook_secret_set'] ? 'text-brand-bright' : 'text-mist' }}">
                                {{ $telegram['webhook_secret_set'] ? 'sim' : 'não' }}
                            </span>
                            · Admins logados:
                            <span class="{{ ($telegram['linked_admins'] ?? 0) > 0 ? 'text-brand-bright' : 'text-mist' }}">
                                {{ $telegram['linked_admins'] ?? 0 }}
                            </span>
                        </p>
                        @if(! empty($telegram['webhook_url']))
                            <p class="mt-1 break-all">URL do webhook: <code class="text-snow">{{ $telegram['webhook_url'] }}</code></p>
                        @endif
                    </div>
                </div>

                <x-admin.inline-docs title="Como ativar o bot" class="admin-docs--sticky">
                    <details class="admin-docs__details" open>
                        <summary>Setup do bot</summary>
                        <ol>
                            <li>Crie o bot no @BotFather e copie o token.</li>
                            <li>No <code>.env</code>: <code>TELEGRAM_BOT_TOKEN</code>, <code>TELEGRAM_BOT_USERNAME</code> (ex. <code>buri_tibot</code>), <code>TELEGRAM_WEBHOOK_SECRET</code>.</li>
                            <li>Com <code>APP_URL</code> HTTPS (ex. com <code>/public</code>): <code>php artisan telegram:configure</code>. Se o nome der rate limit: <code>--skip-name --drop-pending</code>.</li>
                            <li>No bot: <code>/login email_ou_usuario | senha</code> — só contas admin. A mensagem com senha é apagada.</li>
                            <li>No site, use só “Continuar com Telegram” (desafio temporário no bot). <code>/logout</code> encerra a sessão do bot.</li>
                            <li>Comandos: <code>/ajuda</code>, listas e <code>add</code>/<code>set</code>/<code>del</code>.</li>
                        </ol>
                    </details>
                    <details class="admin-docs__details">
                        <summary>Lembretes de tarefas</summary>
                        <p>A cada 5 min o sistema avisa no Telegram quem <strong>criou</strong> a tarefa, ~10 min antes do prazo.</p>
                        <ol>
                            <li>Admin com <code>/login</code> no bot (precisa de <code>telegram_chat_id</code>).</li>
                            <li>Cron obrigatório: <code>* * * * * cd /caminho && php artisan schedule:run</code></li>
                            <li>Teste: <code>php artisan tasks:telegram-reminders</code></li>
                        </ol>
                        <p class="admin-docs__note">Fuso: <code>APP_TIMEZONE=America/Sao_Paulo</code>. Cada tarefa notifica uma vez; mudar o prazo permite novo aviso.</p>
                    </details>
                </x-admin.inline-docs>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admin.settings.edit') }}#google-integration" class="text-sm text-brand-bright hover:underline">Configurar Google Agenda (com documentação) →</a>
            <x-ui.button type="submit">Salvar integrações</x-ui.button>
        </div>
    </form>

    <section class="mt-12">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Roadmap</p>
        <h2 class="mt-2 font-display text-2xl font-bold">Como usar as ferramentas com o site</h2>
        <p class="mt-2 max-w-2xl text-sm text-mist">
            Sugestões de fluxo BURI-TI: do formulário público à operação, ligando quem (CRM) ao quê (projetos) e quando (agenda/Trello).
        </p>

        <ol class="mt-8 space-y-5">
            @foreach($roadmap as $step)
                <li class="rounded-sm border border-line bg-panel p-5 sm:p-6">
                    <div class="flex flex-wrap items-start gap-4">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-sm border border-brand/40 bg-brand/10 font-display text-lg font-bold text-brand-bright">
                            {{ $step['phase'] }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-display text-lg font-semibold text-snow">{{ $step['title'] }}</h3>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($step['tools'] as $tool)
                                    <span class="rounded-sm border border-line px-2 py-0.5 text-[11px] uppercase tracking-wide text-mist">{{ $tool }}</span>
                                @endforeach
                            </div>
                            <ul class="mt-4 space-y-2">
                                @foreach($step['items'] as $item)
                                    <li class="flex gap-2 text-sm text-mist">
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-bright"></span>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endsection
