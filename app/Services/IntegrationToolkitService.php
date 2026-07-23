<?php

namespace App\Services;

use App\Services\SettingService;

class IntegrationToolkitService
{
    public function __construct(private SettingService $settings) {}

    /** @return array{configured: bool, level: int, label: string, next_step: string, board_url: ?string, api_token_set: bool, board_id_set: bool} */
    public function trelloStatus(): array
    {
        $token = filled(config('services.trello.api_key')) && filled(config('services.trello.token'));
        $boardId = filled($this->settings->get('trello_board_id'));
        $boardUrl = $this->settings->get('trello_board_url');
        $level = ($token ? 1 : 0) + ($boardId || filled($boardUrl) ? 1 : 0);

        return [
            'configured' => $level >= 1,
            'level' => $level,
            'label' => match ($level) {
                2 => 'Trello pronto (API + board)',
                1 => $token ? 'API Trello configurada' : 'Board Trello ligado',
                default => 'Trello por configurar',
            },
            'next_step' => match ($level) {
                2 => 'Use o board para cartões de entrega ligados a projetos e CRM.',
                1 => $token
                    ? 'Indique o Board ID ou URL do board nas integrações.'
                    : 'Adicione TRELLO_API_KEY e TRELLO_TOKEN no .env.',
                default => 'Configure a API no .env e o board abaixo.',
            },
            'board_url' => $boardUrl,
            'api_token_set' => $token,
            'board_id_set' => $boardId,
        ];
    }

    /** @return array{configured: bool, level: int, label: string, next_step: string, workspace_url: ?string, token_set: bool, database_id_set: bool} */
    public function notionStatus(): array
    {
        $token = filled(config('services.notion.token'));
        $databaseId = filled($this->settings->get('notion_database_id'));
        $workspaceUrl = $this->settings->get('notion_workspace_url');
        $level = ($token ? 1 : 0) + ($databaseId || filled($workspaceUrl) ? 1 : 0);

        return [
            'configured' => $level >= 1,
            'level' => $level,
            'label' => match ($level) {
                2 => 'Notion pronto (token + base)',
                1 => $token ? 'Token Notion configurado' : 'Workspace Notion ligado',
                default => 'Notion por configurar',
            },
            'next_step' => match ($level) {
                2 => 'Use a base para briefs, propostas e documentação de projetos.',
                1 => $token
                    ? 'Indique o Database ID ou URL do workspace.'
                    : 'Adicione NOTION_TOKEN no .env (integração interna).',
                default => 'Configure o token no .env e a base/workspace abaixo.',
            },
            'workspace_url' => $workspaceUrl,
            'token_set' => $token,
            'database_id_set' => $databaseId,
        ];
    }

    /**
     * @return array{
     *     configured: bool,
     *     level: int,
     *     label: string,
     *     next_step: string,
     *     token_set: bool,
     *     webhook_secret_set: bool,
     *     allowed_chats_set: bool,
     *     notify_chat_set: bool,
     *     webhook_url: ?string
     * }
     */
    public function telegramStatus(): array
    {
        $token = filled(config('services.telegram.bot_token'));
        $secret = filled(config('services.telegram.webhook_secret'));
        $allowed = filled($this->settings->get('telegram_allowed_chat_ids'));
        $notify = filled($this->settings->get('telegram_notify_chat_id'));
        $level = ($token ? 1 : 0) + ($secret ? 1 : 0) + ($allowed ? 1 : 0);

        $webhookUrl = null;
        if ($secret) {
            $webhookUrl = rtrim((string) config('app.url'), '/').'/webhooks/telegram/'.config('services.telegram.webhook_secret');
        }

        return [
            'configured' => $level >= 2,
            'level' => $level,
            'label' => match ($level) {
                3 => 'Telegram pronto (bot + webhook + chats)',
                2 => $allowed ? 'Bot e chats OK — falta webhook secret' : 'Bot OK — autorize os chats',
                1 => $token ? 'Token do bot definido' : 'Segredo do webhook definido',
                default => 'Telegram por configurar',
            },
            'next_step' => match (true) {
                $level >= 3 => 'Use /ajuda no bot. Mensagens do site chegam ao chat de notificação.',
                ! $token => 'Crie o bot no @BotFather e defina TELEGRAM_BOT_TOKEN no .env.',
                ! $secret => 'Defina TELEGRAM_WEBHOOK_SECRET e rode php artisan telegram:set-webhook.',
                ! $allowed => 'Envie /id no bot e cole o Chat ID em “Chats autorizados”.',
                default => 'Complete token, secret e chats autorizados.',
            },
            'token_set' => $token,
            'webhook_secret_set' => $secret,
            'allowed_chats_set' => $allowed,
            'notify_chat_set' => $notify,
            'webhook_url' => $webhookUrl,
        ];
    }

    /**
     * Roadmap sugerido de uso das ferramentas com o site BURI-TI.
     *
     * @return list<array{phase: string, title: string, tools: list<string>, items: list<string>}>
     */
    public function roadmap(): array
    {
        return [
            [
                'phase' => '1',
                'title' => 'Captura e qualificação',
                'tools' => ['Site', 'CRM', 'Telegram', 'Notion'],
                'items' => [
                    'Formulário de contato cria lead no CRM, atividade automática e avisa o bot Telegram.',
                    'No Telegram, use /contato e /oportunidade para registar leads em movimento.',
                    'No Notion, abra uma página “Brief do lead” com contexto e perguntas de discovery.',
                ],
            ],
            [
                'phase' => '2',
                'title' => 'Planejamento da entrega',
                'tools' => ['Trello', 'Tarefas', 'Telegram', 'Google Agenda'],
                'items' => [
                    'Crie um cartão no Trello por oportunidade (lista: Discovery / Proposta / Execução).',
                    'Espelhe marcos com /tarefa no bot ou no kanban do admin; sincronize com Google Agenda/Meet.',
                    'Vincule o contato CRM à tarefa para manter “quem” ligado ao “quando”.',
                ],
            ],
            [
                'phase' => '3',
                'title' => 'Execução e produtos',
                'tools' => ['Projetos', 'Trello', 'Notion'],
                'items' => [
                    'Cadastre o produto/case em Projetos (público ou repo privado) — também via /projeto.',
                    'No Trello, mova cartões conforme o kanban interno (To do / Doing / Done).',
                    'Documente decisões e handoff no Notion; ligue a URL na oportunidade ou notas do contato.',
                ],
            ],
            [
                'phase' => '4',
                'title' => 'Pós-venda e operação',
                'tools' => ['CRM', 'Mensagens', 'Telegram', 'Agenda'],
                'items' => [
                    'Registe chamadas/reuniões como atividades no contato.',
                    'Use oportunidades won/lost para medir conversão.',
                    'Agende follow-ups no Google Agenda a partir das tarefas abertas.',
                ],
            ],
            [
                'phase' => '5',
                'title' => 'Melhoria contínua',
                'tools' => ['Integrações', 'Configurações'],
                'items' => [
                    'Eleve o nível Trello/Notion/Telegram (API + board/base + chats) para automação.',
                    'Revise o roadmap trimestralmente: o que vira checklist padrão da BURI-TI.',
                    'Mantenha canais do site (WhatsApp, e-mail, Telegram) alinhados nas Configurações.',
                ],
            ],
        ];
    }
}
