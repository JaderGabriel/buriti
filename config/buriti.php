<?php

return [
    'contact' => [
        'email' => 'jadergabriel8@gmail.com',
        'phone' => '+55 38 99175-8416',
        'whatsapp' => '+55 38991758416',
        'linkedin_url' => 'https://www.linkedin.com/in/jadergabriel/',
        'github_url' => 'https://github.com/JaderGabriel',
        'telegram_url' => 'https://t.me/JaderGabriel',
        'telegram_handle' => '@JaderGabriel',
    ],

    'google_calendar_url' => 'https://calendar.google.com/calendar/u/0/r',

    'services' => [
        [
            'title' => 'Consultoria em TI',
            'description' => 'Diagnóstico de necessidades e roadmap tecnológico sob medida para o seu negócio.',
        ],
        [
            'title' => 'Desenvolvimento de Software',
            'description' => 'Aplicações web e integrações Laravel alinhadas ao processo real da operação.',
        ],
        [
            'title' => 'Business Intelligence',
            'description' => 'Painéis, indicadores e modelagem de dados para decisão gerencial com Power BI e bases educacionais.',
        ],
        [
            'title' => 'Gestão de Projetos',
            'description' => 'Entrega ágil com priorização, transparência e prazos realistas.',
        ],
        [
            'title' => 'Suporte e Integrações',
            'description' => 'Suporte contínuo, pontes entre sistemas (i-Educar, biometria, catracas) e operação estável.',
        ],
        [
            'title' => 'Treinamentos',
            'description' => 'Capacitação prática para maximizar o uso das tecnologias já contratadas.',
        ],
    ],

    /*
    | Modelagem gerencial + técnica da experiência BURI-TI / Jader Gabriel
    */
    'expertise' => [
        'intro' => 'Atuação que une visão gerencial (projetos, processos e educação corporativa) com entrega técnica (software, dados e BI) — do diagnóstico à operação.',
        'managerial' => [
            [
                'title' => 'Análise de Sistemas e Projetos',
                'level' => 95,
                'description' => 'Levantamento de requisitos, escopo, priorização e alinhamento entre negócio e TI.',
            ],
            [
                'title' => 'Gestão ágil de entregas',
                'level' => 90,
                'description' => 'Planejamento, acompanhamento e comunicação com stakeholders em ciclos curtos.',
            ],
            [
                'title' => 'Educação corporativa',
                'level' => 88,
                'description' => 'Capacitação de equipes e transferência de conhecimento para sustentar a solução.',
            ],
            [
                'title' => 'Governança de dados educacionais',
                'level' => 85,
                'description' => 'Indicadores por município, qualidade de base e apoio à decisão da gestão pública/privada.',
            ],
        ],
        'technical' => [
            [
                'group' => 'Backend e web',
                'items' => ['PHP', 'Laravel', 'Blade', 'APIs REST', 'Filas e jobs'],
            ],
            [
                'group' => 'Dados e BI',
                'items' => ['Power BI', 'Modelagem dimensional', 'ETL / importações', 'Dashboards', 'SQL analítico'],
            ],
            [
                'group' => 'Bases e infraestrutura',
                'items' => ['MySQL', 'PostgreSQL', 'Linux', 'Docker (ambientes)', 'Integrações multi-base'],
            ],
            [
                'group' => 'Ecossistema educacional',
                'items' => ['i-Educar', 'Educacenso/INEP', 'Biometria / catracas', 'Monitoramento (Pulse)', 'Relatórios avançados'],
            ],
            [
                'group' => 'Outras linguagens',
                'items' => ['Java', 'Python', 'JavaScript', 'Shell', 'C (bases acadêmicas)'],
            ],
        ],
        'bi' => [
            [
                'title' => 'Power BI + i-Educar',
                'description' => 'Pacote de BI para transformar dados operacionais em painéis de gestão escolar e indicadores.',
            ],
            [
                'title' => 'Servlitcys — análise municipal',
                'description' => 'Plataforma Laravel com painéis por município e ligação a bases i-Educar (MySQL/PostgreSQL).',
            ],
            [
                'title' => 'Importação Educacenso',
                'description' => 'Pipeline de importação INEP/Educacenso para alimentar a base i-Educar com consistência.',
            ],
            [
                'title' => 'Monitoramento operacional',
                'description' => 'Pulse e relatórios avançados para acompanhar saúde do sistema e uso em produção.',
            ],
        ],
    ],

    'portfolio' => [
        [
            'name' => 'Servlitcys',
            'category' => 'BI & Painéis',
            'information' => 'Plataforma web Laravel para dados educacionais por município: painéis, análise e ligação a bases i-Educar por cidade (MySQL ou PostgreSQL conforme a configuração).',
            'website_url' => 'https://analise.serventecassessoria.com.br',
            'github_url' => 'https://github.com/JaderGabriel/serventec-servlitcys',
            'stack' => ['Laravel', 'PHP', 'MySQL', 'PostgreSQL', 'Dashboards'],
            'status' => 'active',
            'is_public' => true,
            'sort_order' => 1,
        ],
        [
            'name' => 'i-Educar Power BI',
            'category' => 'Business Intelligence',
            'information' => 'Pacote de BI para o i-Educar: exposição e organização de dados para indicadores e acompanhamento gerencial no Power BI.',
            'website_url' => null,
            'github_url' => 'https://github.com/JaderGabriel/i-educar-powerbi-package',
            'stack' => ['PHP', 'Power BI', 'i-Educar', 'SQL'],
            'status' => 'active',
            'is_public' => true,
            'sort_order' => 2,
        ],
        [
            'name' => 'i-Educar Pulse',
            'category' => 'Monitoramento',
            'information' => 'Pacote de monitoramento para i-Educar, apoiando operação contínua e visibilidade sobre o ambiente em produção.',
            'website_url' => null,
            'github_url' => 'https://github.com/JaderGabriel/i-educar-pulse-package',
            'stack' => ['PHP', 'i-Educar', 'Observabilidade'],
            'status' => 'active',
            'is_public' => true,
            'sort_order' => 3,
        ],
        [
            'name' => 'GIDE',
            'category' => 'Integração',
            'information' => 'Ponte lógica entre i-Educar e controle de acesso — integração de fluxos educacionais com segurança física/lógica.',
            'website_url' => null,
            'github_url' => 'https://github.com/JaderGabriel/GIDE',
            'stack' => ['Laravel', 'Blade', 'i-Educar', 'Integrações'],
            'status' => 'active',
            'is_public' => true,
            'sort_order' => 4,
        ],
        [
            'name' => 'Catraca & Frequência',
            'category' => 'Integração',
            'information' => 'Integração i-Educar ↔ GIDE ↔ Biometria para registo de frequência e controlo de acesso em ambiente escolar.',
            'website_url' => null,
            'github_url' => 'https://github.com/JaderGabriel/i-educar-catraca-frequencia-package',
            'stack' => ['PHP', 'Biometria', 'i-Educar', 'GIDE'],
            'status' => 'active',
            'is_public' => true,
            'sort_order' => 5,
        ],
        [
            'name' => 'Importação Educacenso',
            'category' => 'Dados / ETL',
            'information' => 'Importa dados do INEP/Educacenso para o i-Educar (1ª etapa), reduzindo trabalho manual e erros de carga.',
            'website_url' => null,
            'github_url' => 'https://github.com/JaderGabriel/i-educar-educacenso-import-package',
            'stack' => ['PHP', 'ETL', 'INEP', 'i-Educar'],
            'status' => 'active',
            'is_public' => true,
            'sort_order' => 6,
        ],
        [
            'name' => 'Eduque',
            'category' => 'Gestão educacional',
            'information' => 'Sistema de Gestão Educacional open-source — projeto de TCC (2015), base da trajetória em software educacional.',
            'website_url' => null,
            'github_url' => 'https://github.com/JaderGabriel/Eduque',
            'stack' => ['PHP', 'SGE', 'Open Source'],
            'status' => 'done',
            'is_public' => true,
            'sort_order' => 7,
        ],
        [
            'name' => 'ProEvent',
            'category' => 'Gestão',
            'information' => 'Sistema de Gestão de Eventos — organização de atividades, participantes e operação de eventos.',
            'website_url' => null,
            'github_url' => 'https://github.com/JaderGabriel/ProEvent',
            'stack' => ['PHP', 'Gestão de Eventos'],
            'status' => 'done',
            'is_public' => true,
            'sort_order' => 8,
        ],
    ],
];
