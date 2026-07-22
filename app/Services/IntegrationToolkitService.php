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
                'tools' => ['Site', 'CRM', 'Notion'],
                'items' => [
                    'Formulário de contato cria lead no CRM e atividade automática.',
                    'No Notion, abra uma página “Brief do lead” com contexto e perguntas de discovery.',
                    'Marque o estágio da oportunidade no CRM (lead → qualified).',
                ],
            ],
            [
                'phase' => '2',
                'title' => 'Planejamento da entrega',
                'tools' => ['Trello', 'Tarefas', 'Google Agenda'],
                'items' => [
                    'Crie um cartão no Trello por oportunidade (lista: Discovery / Proposta / Execução).',
                    'Espelhe marcos em Tarefas do admin e sincronize com Google Agenda/Meet.',
                    'Vincule o contato CRM à tarefa para manter “quem” ligado ao “quando”.',
                ],
            ],
            [
                'phase' => '3',
                'title' => 'Execução e produtos',
                'tools' => ['Projetos', 'Trello', 'Notion'],
                'items' => [
                    'Cadastre o produto/case em Projetos (público ou repo privado).',
                    'No Trello, mova cartões conforme o kanban interno (To do / Doing / Done).',
                    'Documente decisões e handoff no Notion; ligue a URL na oportunidade ou notas do contato.',
                ],
            ],
            [
                'phase' => '4',
                'title' => 'Pós-venda e operação',
                'tools' => ['CRM', 'Mensagens', 'Agenda'],
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
                    'Eleve o nível Trello/Notion (API + board/base) para automação futura.',
                    'Revise o roadmap trimestralmente: o que vira checklist padrão da BURI-TI.',
                    'Mantenha canais do site (WhatsApp, e-mail, Telegram) alinhados nas Configurações.',
                ],
            ],
        ];
    }
}
