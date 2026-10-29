<?php

namespace App\Support;

final class ProductRoadmap
{
    /**
     * @return array<string, mixed>
     */
    public static function page(): array
    {
        $ideas = self::ideas();

        return [
            'ideas' => $ideas,
            'waves' => self::waves(),
            'competitors' => self::competitors(),
            'currentModules' => self::currentModules(),
            'proposedModules' => self::proposedModules(),
            'deliveryLoop' => self::deliveryLoop(),
            'impacts' => self::systemImpacts(),
            'outOfScope' => self::outOfScope(),
            'summary' => self::summary($ideas),
            'filters' => self::filters(),
            'scoring' => self::scoringHelp(),
            'product' => RuntimeVersions::product(),
            'currentRelease' => self::currentRelease(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function ideas(): array
    {
        return array_map(self::hydrate(...), self::catalog());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function waves(): array
    {
        return [
            [
                'id' => 'agora',
                'label' => 'Agora',
                'horizon' => '1–2 meses',
                'goal' => 'Fechar o loop comercial com o que o funil já tem, sem novos módulos.',
            ],
            [
                'id' => 'proximo',
                'label' => 'Próximo',
                'horizon' => 'trimestre',
                'goal' => 'Submódulos que pedem schema e papéis, ainda dentro de Comercial, Entrega, Jirau e Sistema.',
            ],
            [
                'id' => 'escala',
                'label' => 'Escala',
                'horizon' => 'depois',
                'goal' => 'Módulos novos só quando propostas, horas e papéis já existirem.',
            ],
        ];
    }

    /**
     * @return list<array{name: string, kind: string, take: string}>
     */
    public static function competitors(): array
    {
        return [
            [
                'name' => 'Pipedrive',
                'kind' => 'CRM de vendas',
                'take' => 'Pipeline visual, actividades com prazo, quotes e automações curtas. O BURI-TI já tem o board; faltam proposta formal e SLA de follow-up.',
            ],
            [
                'name' => 'HubSpot',
                'kind' => 'CRM + inbound',
                'take' => 'Sequências, relatórios e inbox omnicanal. Copiar o marketing automation inteiro seria ruído; copiar sequências de follow-up e um painel de conversão, não.',
            ],
            [
                'name' => 'RD Station / Agendor',
                'kind' => 'CRM BR',
                'take' => 'WhatsApp como canal de venda, scoring simples e mobile. O canal que o mercado brasileiro espera e o site ainda trata só como link.',
            ],
            [
                'name' => 'Jira / Linear',
                'kind' => 'Entrega',
                'take' => 'Ciclos, WIP e SLA. O Jirau já é o quadro; templates e prazo por coluna fecham o que Linear faz com pouco ecrã.',
            ],
            [
                'name' => 'ClickUp / Monday',
                'kind' => 'Work OS',
                'take' => 'Tudo-em-um. Útil como antimodelo: o BURI-TI perde se virar suíte genérica. Pegar automações pontuais, não a superfície infinita.',
            ],
            [
                'name' => 'Accelo / Scoro',
                'kind' => 'PSA de agência',
                'take' => 'Proposta → contrato → horas → fatura no mesmo sítio. É o ciclo que uma consultoria de TI realmente fecha — o alvo de médio prazo.',
            ],
            [
                'name' => 'Take Blip / Z-API',
                'kind' => 'WhatsApp',
                'take' => 'API oficial ou ponte. O valor está na ficha do contacto, não no chatbot de marketing.',
            ],
            [
                'name' => 'Odoo / Conta Azul',
                'kind' => 'Financeiro',
                'take' => 'Contas a receber e impostos. Só depois de proposta e apontamento; senão o BURI-TI vira ERP pela porta dos fundos.',
            ],
            [
                'name' => 'Moodle / i-Educar',
                'kind' => 'Clientes, não CRM',
                'take' => 'Onde a BURI-TI entrega. Indicadores educacionais no painel são diferenciação que HubSpot e Pipedrive não têm.',
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string, items: list<string>}>
     */
    public static function currentModules(): array
    {
        return [
            ['id' => 'home', 'label' => 'Início', 'items' => ['Dashboard', 'Próximas acções', 'Funil']],
            ['id' => 'comercial', 'label' => 'Comercial', 'items' => ['Mensagens', 'Empresas', 'Contatos', 'Oportunidades']],
            ['id' => 'entrega', 'label' => 'Entrega', 'items' => ['Projetos', 'Vitrine', 'Agenda']],
            ['id' => 'jirau', 'label' => 'Jirau', 'items' => ['Quadros', 'Visão geral', 'Analítica / horas', 'Equipe']],
            ['id' => 'sistema', 'label' => 'Sistema', 'items' => ['Usuários', 'Integrações', 'Configurações', 'E-mail', 'Roadmap', 'Testes']],
        ];
    }

    /**
     * @return list<array{id: string, label: string, state: string, items: list<string>}>
     */
    public static function proposedModules(): array
    {
        return [
            ['id' => 'home', 'label' => 'Início', 'state' => 'keep', 'items' => ['Dashboard', 'Funil', 'Relatório comercial']],
            ['id' => 'comercial', 'label' => 'Comercial', 'state' => 'grow', 'items' => ['Inbox unificada', 'WhatsApp', 'Propostas', 'Playbooks']],
            ['id' => 'entrega', 'label' => 'Entrega', 'state' => 'grow', 'items' => ['Projetos', 'Contratos', 'Apontamento', 'NPS']],
            ['id' => 'jirau', 'label' => 'Jirau', 'state' => 'grow', 'items' => ['Templates', 'Horas', 'SLA / WIP', 'Quadros']],
            ['id' => 'sistema', 'label' => 'Sistema', 'state' => 'grow', 'items' => ['Papéis', 'Auditoria', 'Roadmap']],
            ['id' => 'portal', 'label' => 'Portal', 'state' => 'new', 'items' => ['Login do cliente', 'Status do projeto', 'Ficheiros']],
            ['id' => 'financeiro', 'label' => 'Financeiro', 'state' => 'new', 'items' => ['Faturas', 'A receber', 'Vínculo à proposta']],
            ['id' => 'conhecimento', 'label' => 'Conhecimento', 'state' => 'new', 'items' => ['Playbooks internos', 'Busca', 'Handoff']],
        ];
    }

    /**
     * @return list<array{title: string, note: string}>
     */
    public static function deliveryLoop(): array
    {
        return [
            ['title' => 'Lead', 'note' => 'Site, WhatsApp ou Telegram na mesma ficha'],
            ['title' => 'Qualificar', 'note' => 'Empresa + contacto + próxima acção'],
            ['title' => 'Proposta', 'note' => 'PDF versionado na oportunidade'],
            ['title' => 'Contrato', 'note' => 'Modelo Drive com revisão humana'],
            ['title' => 'Entregar', 'note' => 'Projeto + quadro Jirau + horas'],
            ['title' => 'Faturar', 'note' => 'A receber a partir das horas'],
            ['title' => 'Renovar', 'note' => 'NPS e indicadores do produto entregue'],
        ];
    }

    /**
     * @return list<array{area: string, agora: string, proximo: string, escala: string}>
     */
    public static function systemImpacts(): array
    {
        return [
            [
                'area' => 'Navegação admin',
                'agora' => 'Um item no Sistema (esta página) e, no máximo, um relatório no Comercial.',
                'proximo' => 'Submódulos novos em Comercial, Entrega, Jirau e Sistema. Cores de módulo já existentes aguentam.',
                'escala' => 'Três módulos novos (Portal, Financeiro, Conhecimento) na barra — rail colapsado e busca precisam de atalhos extra.',
            ],
            [
                'area' => 'Schema / migrations',
                'agora' => 'Quase nenhum: views e jobs sobre oportunidades, actividades e audit_logs.',
                'proximo' => 'quotes, time_entries, roles/permissions, contract_docs, nps_responses. Risco de FKs em contacts/opportunities/projects.',
                'escala' => 'invoices, portal_users, knowledge_articles, credenciais Moodle por cliente. Maior superfície de backup e LGPD.',
            ],
            [
                'area' => 'Jobs, Telegram, Google',
                'agora' => 'Alertas de follow-up já existem; relatórios e templates não mexem no bot.',
                'proximo' => 'Playbooks geram actividades e ruído no Telegram. Contratos pedem scopes Drive. Horas podem avisar atraso.',
                'escala' => 'Webhooks WhatsApp e Moodle. Faturas podem exigir NF-e — fora do núcleo Laravel actual.',
            ],
            [
                'area' => 'Site público',
                'agora' => 'Intacto.',
                'proximo' => 'Formulário NPS por token. Nada de marketing site-wide.',
                'escala' => 'Portal autenticado (novo guard) e, se houver WhatsApp, página de privacidade a actualizar.',
            ],
            [
                'area' => 'Testes e operação',
                'agora' => 'Poucos testes de leitura + Pint. Sem seed novo.',
                'proximo' => 'Policies em todo o admin; factories de proposta/hora; regressão de reunião/actividade (ver consolidação do CRM).',
                'escala' => 'Dois públicos (staff vs cliente). Suite e seeds crescem; 2FA e CSP precisam de revisão.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function outOfScope(): array
    {
        return [
            'Virar HubSpot: automação de inbound, landing pages, e-mail marketing em massa.',
            'Virar ClickUp: dezenas de views, docs, chat e metas no mesmo sítio.',
            'ERP completo (estoque, folha, fiscal nativo) antes de proposta e apontamento existirem.',
            'Chatbot de WhatsApp para marketing; o canal entra como inbox ligada ao contacto.',
            'Multi-empresa / white-label. O produto é a operação da BURI-TI, não um SaaS genérico.',
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function filters(): array
    {
        return [
            ['id' => 'all', 'label' => 'Todas'],
            ['id' => 'agora', 'label' => 'Agora'],
            ['id' => 'proximo', 'label' => 'Próximo'],
            ['id' => 'escala', 'label' => 'Escala'],
            ['id' => 'comercial', 'label' => 'Comercial'],
            ['id' => 'entrega', 'label' => 'Entrega'],
            ['id' => 'jirau', 'label' => 'Jirau'],
            ['id' => 'sistema', 'label' => 'Sistema'],
            ['id' => 'novo', 'label' => 'Novos módulos'],
            ['id' => 'modulo', 'label' => 'Módulo'],
            ['id' => 'submodulo', 'label' => 'Submódulo'],
            ['id' => 'recurso', 'label' => 'Recurso'],
            ['id' => 'shipped', 'label' => 'Entregue'],
            ['id' => 'partial', 'label' => 'Parcial'],
        ];
    }

    /**
     * @return array{valor: string, esforco: string, risco: string, indice: string}
     */
    public static function scoringHelp(): array
    {
        return [
            'valor' => '1–5: quanto melhora a operação solo/pequena equipa da BURI-TI (não o mercado genérico).',
            'esforco' => '1 S (~1 semana) · 2 M · 3 L (~1 mês) · 4 XL (trimestre) · 5 XXL.',
            'risco' => '1–5: chance de partir regras já afinadas (reunião/actividade, CSP, Telegram, papéis).',
            'indice' => 'índice = (valor × 20 − risco × 4) ÷ esforço. ≥ 28 alta · ≥ 18 média · resto baixa.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $ideas
     * @return array<string, mixed>
     */
    public static function summary(array $ideas): array
    {
        $byWave = ['agora' => 0, 'proximo' => 0, 'escala' => 0];
        $byKind = ['modulo' => 0, 'submodulo' => 0, 'recurso' => 0];
        $sum = 0;

        foreach ($ideas as $idea) {
            $wave = (string) ($idea['wave'] ?? '');
            $kind = (string) ($idea['kind'] ?? '');
            if (isset($byWave[$wave])) {
                $byWave[$wave]++;
            }
            if (isset($byKind[$kind])) {
                $byKind[$kind]++;
            }
            $sum += (int) ($idea['indice'] ?? 0);
        }

        $count = max(1, count($ideas));

        return [
            'total' => count($ideas),
            'avg_indice' => (int) round($sum / $count),
            'by_wave' => $byWave,
            'by_kind' => $byKind,
        ];
    }

    /**
     * @param  array<string, mixed>  $idea
     * @return array<string, mixed>
     */
    private static function hydrate(array $idea): array
    {
        $valor = (int) $idea['valor'];
        $esforco = (int) $idea['esforco'];
        $risco = (int) $idea['risco'];
        $indice = (int) round(($valor * 20 - $risco * 4) / max(1, $esforco));

        $idea['indice'] = $indice;
        $idea['prioridade'] = $indice >= 28 ? 'alta' : ($indice >= 18 ? 'média' : 'baixa');
        $idea['esforco_label'] = [1 => 'S', 2 => 'M', 3 => 'L', 4 => 'XL', 5 => 'XXL'][$esforco] ?? (string) $esforco;
        $idea['kind_label'] = [
            'modulo' => 'Novo módulo',
            'submodulo' => 'Submódulo',
            'recurso' => 'Recurso',
        ][$idea['kind'] ?? ''] ?? 'Ideia';
        $idea['wave_label'] = [
            'agora' => 'Agora',
            'proximo' => 'Próximo',
            'escala' => 'Escala',
        ][$idea['wave'] ?? ''] ?? '';
        $idea['module_label'] = [
            'comercial' => 'Comercial',
            'entrega' => 'Entrega',
            'jirau' => 'Jirau',
            'sistema' => 'Sistema',
            'novo' => 'Novo módulo',
        ][$idea['module'] ?? ''] ?? '';
        $idea['status'] = (string) ($idea['status'] ?? 'planned');
        $idea['status_label'] = match ($idea['status']) {
            'shipped' => 'Entregue',
            'partial' => 'Parcial',
            default => 'Planejado',
        };

        return $idea;
    }

    /**
     * @return array{short: string, release: string, headline: string, items: list<string>}
     */
    public static function currentRelease(): array
    {
        return [
            'short' => RuntimeVersions::short(),
            'release' => RuntimeVersions::release(),
            'headline' => 'Correio da conta, cópia em Enviados e aviso LGPD em cada envio.',
            'items' => [
                'Cliente IMAP só para o admin: pastas, leitura, envio, respostas ao site e rascunhos, com a identidade do login SMTP.',
                'O envio guarda cópia em Enviados (APPEND com tamanho exacto; Gmail/Outlook usam a pasta do provedor).',
                'Modelo claro com assinatura pronta e rodapé de confidencialidade + Lei nº 13.709/2018 (LGPD).',
                'Busca da pasta isolada da busca do painel; apagar o critério volta a mostrar todas as mensagens.',
                'Bot: /correio, /email e /mensagem responder pela mesma conta.',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function catalog(): array
    {
        return [
            [
                'id' => 'relatorio-comercial',
                'title' => 'Relatório de conversão',
                'kind' => 'submodulo',
                'module' => 'comercial',
                'wave' => 'agora',
                'valor' => 4,
                'esforco' => 2,
                'risco' => 1,
                'inspired' => 'HubSpot reports, Agendor, funil do Pipedrive',
                'summary' => 'Página de leitura sobre o FunnelMetricsService: win rate, tempo em estágio, origem (site vs Telegram) e valor ganho. Sem schema novo.',
                'impact' => 'Quase só queries e um item de menu. Não mexe em políticas de reunião. Pode reutilizar o filtro de empresa do dashboard. Testes de snapshot, não de escrita.',
                'surfaces' => ['Comercial', 'FunnelMetricsService', 'CSV já existente'],
            ],
            [
                'id' => 'templates-jirau',
                'title' => 'Templates de quadro Jirau',
                'kind' => 'recurso',
                'module' => 'jirau',
                'wave' => 'agora',
                'status' => 'shipped',
                'shipped_in' => '1.6',
                'valor' => 4,
                'esforco' => 2,
                'risco' => 1,
                'inspired' => 'Trello, ClickUp, Monday',
                'summary' => 'Entregue: clonar um quadro (colunas, prazos padrão, checklists) a partir de um modelo Moodle, i-Educar, consultoria ou site Laravel.',
                'impact' => 'JSON de templates + clone transaccional. Schema de colunas já tinha cor, ordem e default_due.',
                'surfaces' => ['Jirau', 'colunas', 'checklists'],
            ],
            [
                'id' => 'inbox-app',
                'title' => 'Inbox in-app',
                'kind' => 'recurso',
                'module' => 'sistema',
                'wave' => 'agora',
                'status' => 'shipped',
                'shipped_in' => '1.7',
                'valor' => 4,
                'esforco' => 2,
                'risco' => 1,
                'inspired' => 'Linear, GitHub, Slack',
                'summary' => 'Entregue: sino ao lado do perfil, caixa persistida, lidas, eventos de Jirau, site e agenda. Telegram continua.',
                'impact' => 'Tabela user_notifications + sino na topbar admin/Jirau. Sem módulo novo na barra.',
                'surfaces' => ['Topbar', 'Jirau', 'Mensagens', 'Agenda'],
            ],
            [
                'id' => 'follow-up-sla',
                'title' => 'SLA de follow-up',
                'kind' => 'recurso',
                'module' => 'comercial',
                'wave' => 'agora',
                'valor' => 4,
                'esforco' => 2,
                'risco' => 2,
                'inspired' => 'Pipedrive activities, RD Station tarefas',
                'summary' => 'Prazo máximo sem próxima acção por estágio da oportunidade, com destaque no inbox e no Telegram. O job de follow-up já existe.',
                'impact' => 'Campos de política em settings ou no estágio. Risco baixo se não auto-concluir reuniões. Alinha com a consolidação do CRM (não fechar actividade por acidente).',
                'surfaces' => ['Oportunidades', 'inbox', 'Telegram', 'settings'],
            ],
            [
                'id' => 'auditoria-ui',
                'title' => 'Auditoria visível',
                'kind' => 'submodulo',
                'module' => 'sistema',
                'wave' => 'agora',
                'valor' => 3,
                'esforco' => 2,
                'risco' => 1,
                'inspired' => 'Salesforce setup audit trail',
                'summary' => 'Lista filtrável de audit_logs (quem, o quê, quando) dentro de Sistema. A tabela e o logger já existem.',
                'impact' => 'Controller + Blade + índice de busca. Zero schema. Ajuda suporte e LGPD sem tocar no funil.',
                'surfaces' => ['Sistema', 'audit_logs', 'busca admin'],
            ],
            [
                'id' => 'propostas',
                'title' => 'Propostas / orçamentos',
                'kind' => 'submodulo',
                'module' => 'comercial',
                'wave' => 'agora',
                'valor' => 5,
                'esforco' => 3,
                'risco' => 2,
                'inspired' => 'Pipedrive Quotes, RD Station, Accelo',
                'summary' => 'Versões de proposta ligadas à oportunidade: itens, validade, PDF e pasta Drive. É o artefacto que hoje vive no e-mail.',
                'impact' => 'Tabela quotes + PDF (DomPDF ou Blade) + pasta da oportunidade. Nav Comercial ganha um submódulo. Não substitui contrato. Testes de versionamento e de não vazar PDF sem auth.',
                'surfaces' => ['Oportunidades', 'Drive', 'PDF', 'nav Comercial'],
            ],
            [
                'id' => 'whatsapp-inbox',
                'title' => 'Inbox WhatsApp',
                'kind' => 'submodulo',
                'module' => 'comercial',
                'wave' => 'agora',
                'valor' => 5,
                'esforco' => 3,
                'risco' => 3,
                'inspired' => 'RD Station, Agendor, Take Blip, HubSpot conversations',
                'summary' => 'Webhook da API oficial (ou Z-API) a gravar na ficha do contacto, lado a lado com mensagens do site e Telegram. Sem chatbot de marketing.',
                'impact' => 'Novo canal, idempotência de webhooks, CSP e secrets. Risco de duplicar o inbox. contacts.whatsapp já existe; unificar threads é o trabalho difícil. LGPD: retenção de conversas.',
                'surfaces' => ['Contatos', 'Mensagens', 'webhooks', 'CSP'],
            ],
            [
                'id' => 'nps',
                'title' => 'NPS pós-entrega',
                'kind' => 'recurso',
                'module' => 'entrega',
                'wave' => 'proximo',
                'valor' => 3,
                'esforco' => 2,
                'risco' => 1,
                'inspired' => 'HubSpot Service Hub',
                'summary' => 'Token por projeto concluído, formulário público mínimo, nota na ficha. Alimenta renovação sem CRM de CS.',
                'impact' => 'Uma tabela, uma rota pública autenticada por token, um widget no projeto. Pouca pressão no admin. Actualizar privacidade se guardar comentário.',
                'surfaces' => ['Projetos', 'site público', 'privacidade'],
            ],
            [
                'id' => 'playbooks',
                'title' => 'Playbooks e sequências',
                'kind' => 'recurso',
                'module' => 'comercial',
                'wave' => 'proximo',
                'valor' => 4,
                'esforco' => 3,
                'risco' => 3,
                'inspired' => 'HubSpot workflows, Pipedrive automations',
                'summary' => 'Receita curta: “lead novo → tarefa em 24h → lembrete Telegram”. Sem builder visual tipo ClickUp.',
                'impact' => 'Jobs agendados a criar actividades. Risco alto de ruído no bot e de auto-concluir coisas. Precisa das mesmas regras da consolidação (conclusão explícita).',
                'surfaces' => ['Actividades', 'tarefas', 'Telegram', 'scheduler'],
            ],
            [
                'id' => 'contratos',
                'title' => 'Contratos assistidos',
                'kind' => 'submodulo',
                'module' => 'entrega',
                'wave' => 'proximo',
                'valor' => 4,
                'esforco' => 3,
                'risco' => 3,
                'inspired' => 'Accelo, consolidação CRM §3.1',
                'summary' => 'Copiar modelo Drive, preencher dados da oportunidade/empresa, revisão humana obrigatória. Geração automática sem revisão fica fora.',
                'impact' => 'Scopes Drive já começaram. Submódulo em Entrega (não Comercial) para não misturar venda com jurídico. Risco: tokens Google e pastas partilhadas.',
                'surfaces' => ['Projetos', 'Drive', 'OAuth Google'],
            ],
            [
                'id' => 'apontamento',
                'title' => 'Apontamento de horas',
                'kind' => 'submodulo',
                'module' => 'entrega',
                'wave' => 'proximo',
                'status' => 'partial',
                'valor' => 4,
                'esforco' => 3,
                'risco' => 2,
                'inspired' => 'Accelo, Clockify, Scoro',
                'summary' => 'Parcial: Fazer/Encerrar no cartão Jirau, histórico admin e extrato na analítica já existem. Falta apontamento na ficha do projeto como base de fatura.',
                'impact' => 'jirau_time_entries + analítica. UI na ficha do projeto ainda falta. Sem isto o Financeiro não tem o que faturar no CRM.',
                'surfaces' => ['Jirau', 'Analítica', 'Projetos', 'utilizadores'],
            ],
            [
                'id' => 'sla-jirau',
                'title' => 'SLA e WIP no Jirau',
                'kind' => 'recurso',
                'module' => 'jirau',
                'wave' => 'proximo',
                'valor' => 4,
                'esforco' => 3,
                'risco' => 2,
                'inspired' => 'Jira Service Management, Linear cycles',
                'summary' => 'Limite WIP por coluna (já há ordem/cor) + relógio de permanência e aviso aos watchers.',
                'impact' => 'Campos em jirau_columns + job de aviso. Watchers já existem. Risco: notificações a mais. Analítica passa a ter aging.',
                'surfaces' => ['Jirau colunas', 'watchers', 'analítica'],
            ],
            [
                'id' => 'papeis',
                'title' => 'Papéis além de admin',
                'kind' => 'recurso',
                'module' => 'sistema',
                'wave' => 'proximo',
                'valor' => 5,
                'esforco' => 4,
                'risco' => 3,
                'inspired' => 'HubSpot seats, Salesforce profiles',
                'summary' => 'Comercial, entrega, leitura. Hoje é admin vs Jirau. Sem isto, Portal e Financeiro não podem nascer seguros.',
                'impact' => 'Maior blast radius: policies, nav, Telegram, busca, testes. Não adiciona ecrã bonito — muda o kernel. Fazer antes dos módulos novos.',
                'surfaces' => ['users', 'policies', 'nav', 'Telegram', 'suite de testes'],
            ],
            [
                'id' => 'portal',
                'title' => 'Portal do cliente',
                'kind' => 'modulo',
                'module' => 'novo',
                'wave' => 'escala',
                'valor' => 5,
                'esforco' => 4,
                'risco' => 3,
                'inspired' => 'Accelo client portal, Teamwork',
                'summary' => 'Login do contacto para ver status do projeto, ficheiros e próximas reuniões. Um módulo novo na barra.',
                'impact' => 'Novo guard, rotas públicas autenticadas, papéis obrigatórios. Site e admin deixam de ser os únicos públicos. CSP, 2FA e anexos precisam de regras de visibilidade.',
                'surfaces' => ['auth', 'projetos', 'anexos', 'site'],
            ],
            [
                'id' => 'indicadores-edu',
                'title' => 'Indicadores Moodle / i-Educar',
                'kind' => 'modulo',
                'module' => 'novo',
                'wave' => 'escala',
                'valor' => 5,
                'esforco' => 4,
                'risco' => 3,
                'inspired' => 'Relatórios Moodle; nenhum CRM de vendas faz isto',
                'summary' => 'Painel por cliente com métricas do produto entregue (acessos, turmas, tickets). Diferenciação real da BURI-TI.',
                'impact' => 'Credenciais por empresa, jobs de sync, LGPD (dados de alunos). Não entra no Comercial. Pode viver como submódulo de Entrega se o Portal ainda não existir.',
                'surfaces' => ['Empresas', 'integrações', 'LGPD', 'jobs'],
            ],
            [
                'id' => 'financeiro',
                'title' => 'Financeiro leve',
                'kind' => 'modulo',
                'module' => 'novo',
                'wave' => 'escala',
                'valor' => 4,
                'esforco' => 5,
                'risco' => 4,
                'inspired' => 'Odoo, Conta Azul, Tiny, Scoro',
                'summary' => 'Contas a receber a partir de proposta aceita e horas apontadas. Sem NF-e na primeira fatia.',
                'impact' => 'Módulo novo, schema pesado, conciliação. Só depois de propostas + apontamento + papéis. Risco de o CRM passar a ser contabilidade. Integração bancária fica de fora na v1.',
                'surfaces' => ['nav', 'invoices', 'oportunidades', 'horas'],
            ],
            [
                'id' => 'conhecimento',
                'title' => 'Base de conhecimento',
                'kind' => 'modulo',
                'module' => 'novo',
                'wave' => 'escala',
                'valor' => 3,
                'esforco' => 4,
                'risco' => 2,
                'inspired' => 'Notion, HubSpot Knowledge Base',
                'summary' => 'Playbooks internos pesquisáveis (Moodle, i-Educar, handoff). Alternativa barata: continuar no Notion ligado em Integrações.',
                'impact' => 'Módulo extra na barra e um índice de busca. Pouca sinergia com o funil. Preferir Notion até o Portal precisar de artigos para o cliente.',
                'surfaces' => ['nav', 'busca', 'Integrações/Notion'],
            ],
        ];
    }
}
