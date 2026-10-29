<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\IdeaNote;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauTimeEntry;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\QaRun;
use App\Models\Task;
use App\Models\Testimonial;
use App\Models\User;

final class QaCatalog
{
    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     commands: list<array<string, mixed>>
     * }>
     */
    public static function groups(): array
    {
        return [
            [
                'id' => 'suite',
                'label' => 'Suíte',
                'icon' => 'diagnose',
                'description' => 'PHPUnit isolado (SQLite em memória). Não mexe na base de produção.',
                'commands' => [
                    self::command(
                        'suite.phpunit',
                        'suite',
                        'Rodar suíte completa',
                        'Todos os testes. ~15–30 s. SQLite em memória.',
                        'diagnose',
                        'Correr a suíte PHPUnit agora? Pode levar até 30 segundos.',
                        'phpunit',
                    ),
                    self::command(
                        'suite.unit',
                        'suite',
                        'Suíte unitária',
                        'tests/Unit — PhoneNumber, URLs, regras isoladas.',
                        'code',
                        null,
                        'phpunit',
                        ['args' => ['--testsuite', 'Unit']],
                    ),
                    self::command(
                        'suite.admin',
                        'suite',
                        'Suíte admin / CRM',
                        'tests/Feature/Admin — dashboard, comercial, sistema.',
                        'admin',
                        null,
                        'phpunit',
                        ['args' => ['tests/Feature/Admin']],
                    ),
                    self::command(
                        'suite.jirau',
                        'suite',
                        'Suíte Jirau',
                        'tests/Feature/Jirau — quadros, horas, equipe, evidências.',
                        'jirau',
                        null,
                        'phpunit',
                        ['args' => ['tests/Feature/Jirau']],
                    ),
                    self::command(
                        'suite.testimonials',
                        'suite',
                        'Suíte Depoimentos',
                        'Convite, mediação, selo HMAC, faixa pública e amostras visuais.',
                        'quote',
                        null,
                        'phpunit',
                        ['args' => [
                            'tests/Feature/Admin/TestimonialTest.php',
                            'tests/Unit/TestimonialSealTest.php',
                        ]],
                    ),
                    self::command(
                        'suite.mail',
                        'suite',
                        'Suíte E-mail',
                        'SMTP/IMAP, Correio (caixa, enviados, assinatura, busca da pasta) e e-mail de teste. Convites não disparam.',
                        'mail',
                        null,
                        'phpunit',
                        ['args' => [
                            'tests/Feature/Admin/MailSettingsTest.php',
                            'tests/Feature/Admin/MailboxTest.php',
                            'tests/Unit/MailSettingsServiceTest.php',
                            'tests/Unit/MailDateTest.php',
                            'tests/Unit/ImapClientTest.php',
                            'tests/Unit/ImapMailboxStoreTest.php',
                            'tests/Unit/Rfc822MessageTest.php',
                            'tests/Unit/AuthorizationPolicyTest.php',
                            'tests/Unit/MailBrandingTest.php',
                        ]],
                    ),
                    self::command(
                        'suite.telegram',
                        'suite',
                        'Suíte Telegram',
                        'Bot, login web e lembretes de agenda.',
                        'telegram',
                        null,
                        'phpunit',
                        ['args' => [
                            'tests/Feature/TelegramBotTest.php',
                            'tests/Feature/TelegramWebLoginTest.php',
                            'tests/Feature/TaskTelegramReminderTest.php',
                        ]],
                    ),
                    self::command(
                        'suite.site',
                        'suite',
                        'Suíte site e auth',
                        'Home, legal, formulário, login e 2FA.',
                        'globe',
                        null,
                        'phpunit',
                        ['args' => [
                            'tests/Feature/HomePageTest.php',
                            'tests/Feature/LegalPagesTest.php',
                            'tests/Feature/ContactFormTest.php',
                            'tests/Feature/AuthTest.php',
                            'tests/Feature/AuthTwoFactorTest.php',
                            'tests/Feature/Account',
                        ]],
                    ),
                ],
            ],
            [
                'id' => 'site',
                'label' => 'Site',
                'icon' => 'globe',
                'description' => 'GET nas páginas públicas: saúde, home, legal e login.',
                'commands' => [
                    self::command(
                        'site.pack',
                        'probe',
                        'Telas públicas (lote)',
                        '/up, home, privacidade, cookies e login num relatório.',
                        'globe',
                        null,
                        'http.pack',
                        ['targets' => self::publicTargets()],
                    ),
                    self::command(
                        'health.up',
                        'probe',
                        'Health /up',
                        'GET na rota de saúde da aplicação.',
                        'operate',
                        null,
                        'http',
                        ['path' => '/up'],
                    ),
                    self::command(
                        'health.home',
                        'probe',
                        'Página inicial',
                        'GET na home pública.',
                        'star',
                        null,
                        'http',
                        ['route' => 'home'],
                    ),
                    self::command(
                        'site.privacy',
                        'probe',
                        'Privacidade',
                        'GET em /privacidade.',
                        'eye',
                        null,
                        'http',
                        ['route' => 'privacy'],
                    ),
                    self::command(
                        'site.cookies',
                        'probe',
                        'Cookies',
                        'GET em /cookies.',
                        'folder',
                        null,
                        'http',
                        ['route' => 'cookies'],
                    ),
                    self::command(
                        'health.login',
                        'probe',
                        'Tela de login',
                        'GET na página de entrada do admin.',
                        'admin',
                        null,
                        'http',
                        ['route' => 'login'],
                    ),
                ],
            ],
            [
                'id' => 'comercial',
                'label' => 'Comercial',
                'icon' => 'opportunity',
                'description' => 'Rotas, views e inventário de mensagens, correio IMAP, empresas, contatos e oportunidades.',
                'commands' => [
                    self::command(
                        'crm.health',
                        'probe',
                        'Saúde do comercial',
                        'Confere rotas/views e conta registos. Só leitura.',
                        'opportunity',
                        null,
                        'module',
                        [
                            'routes' => [
                                'admin.dashboard',
                                'admin.messages.index',
                                'admin.mailbox.index',
                                'admin.mailbox.compose',
                                'admin.companies.index',
                                'admin.contacts.index',
                                'admin.opportunities.index',
                                'admin.opportunities.export',
                            ],
                            'views' => [
                                'admin.dashboard',
                                'admin.messages.index',
                                'admin.mailbox.index',
                                'admin.companies.index',
                                'admin.contacts.index',
                                'admin.opportunities.index',
                            ],
                            'counts' => [
                                'Mensagens' => ContactMessage::class,
                                'Empresas' => Company::class,
                                'Contatos' => Contact::class,
                                'Oportunidades' => Opportunity::class,
                                'Actividades CRM' => CrmActivity::class,
                            ],
                        ],
                    ),
                ],
            ],
            [
                'id' => 'entrega',
                'label' => 'Entrega',
                'icon' => 'project',
                'description' => 'Projetos, vitrine da home, agenda, depoimentos e anexos.',
                'commands' => [
                    self::command(
                        'delivery.health',
                        'probe',
                        'Saúde da entrega',
                        'Rotas/views de projetos, vitrine, agenda e depoimentos. Só leitura.',
                        'project',
                        null,
                        'module',
                        [
                            'routes' => [
                                'admin.projects.index',
                                'admin.home-projects.edit',
                                'admin.tasks.index',
                                'admin.tasks.export',
                                'admin.testimonials.index',
                            ],
                            'views' => [
                                'admin.projects.index',
                                'admin.home-projects.edit',
                                'admin.tasks.index',
                                'admin.testimonials.index',
                            ],
                            'counts' => [
                                'Projetos' => Project::class,
                                'Tarefas' => Task::class,
                                'Anexos' => Attachment::class,
                                'Depoimentos' => Testimonial::class,
                            ],
                        ],
                    ),
                ],
            ],
            [
                'id' => 'depoimentos',
                'label' => 'Depoimentos',
                'icon' => 'quote',
                'description' => 'Convites, mediação, selo HMAC, faixa pública na home e amostras visuais.',
                'commands' => [
                    self::command(
                        'testimonials.health',
                        'probe',
                        'Saúde dos depoimentos',
                        'Rotas/views admin e públicas do selo, mais inventário. Só leitura.',
                        'quote',
                        null,
                        'module',
                        [
                            'routes' => [
                                'admin.testimonials.index',
                                'admin.testimonials.create',
                                'admin.testimonials.samples.store',
                                'admin.testimonials.samples.destroy',
                                'testimonials.seal',
                                'testimonials.write',
                                'testimonials.submit',
                                'testimonials.thanks',
                            ],
                            'views' => [
                                'admin.testimonials.index',
                                'admin.testimonials.create',
                                'admin.testimonials.show',
                                'admin.testimonials.partials.sample-tools',
                                'site.partials.quotes',
                                'site.partials.quote-dialog',
                                'site.partials.quote-seal-dialog',
                                'components.site.quote-card',
                                'testimonials.form',
                                'testimonials.thanks',
                                'testimonials.expired',
                                'testimonials.seal',
                            ],
                            'counts' => [
                                'Depoimentos' => Testimonial::class,
                            ],
                        ],
                    ),
                    self::command(
                        'testimonials.seals',
                        'probe',
                        'Selos de autenticidade',
                        'Confere digest SHA-256 e HMAC de cada depoimento selado. Só leitura.',
                        'diagnose',
                        null,
                        'testimonials.seals',
                    ),
                ],
            ],
            [
                'id' => 'jirau',
                'label' => 'Jirau',
                'icon' => 'jirau',
                'description' => 'Quadros, visão geral, analítica, equipe e apontamento.',
                'commands' => [
                    self::command(
                        'jirau.health',
                        'probe',
                        'Saúde do Jirau',
                        'Rotas/views dos quadros e contagens. Só leitura.',
                        'jirau',
                        null,
                        'module',
                        [
                            'routes' => [
                                'jirau.home',
                                'jirau.overview',
                                'jirau.analytics',
                                'jirau.staff',
                            ],
                            'views' => [
                                'jirau.home',
                                'jirau.overview',
                                'jirau.analytics',
                                'jirau.staff',
                            ],
                            'counts' => [
                                'Quadros' => Jirau::class,
                                'Cartões' => JirauCard::class,
                                'Apontamentos' => JirauTimeEntry::class,
                            ],
                        ],
                    ),
                ],
            ],
            [
                'id' => 'sistema',
                'label' => 'Sistema',
                'icon' => 'settings',
                'description' => 'Usuários, configurações, e-mail, stack (DB/cache/disco) e migrations.',
                'commands' => [
                    self::command(
                        'sistema.health',
                        'probe',
                        'Saúde do sistema',
                        'Rotas de usuários, integrações, settings, roadmap e testes.',
                        'settings',
                        null,
                        'module',
                        [
                            'routes' => [
                                'admin.users.index',
                                'admin.integrations.edit',
                                'admin.settings.edit',
                                'admin.settings.mail',
                                'admin.roadmap.show',
                                'admin.qa.index',
                                'admin.profile.edit',
                            ],
                            'views' => [
                                'admin.users.index',
                                'admin.integrations.edit',
                                'admin.settings.edit',
                                'admin.settings.mail',
                                'admin.roadmap.show',
                                'admin.qa.index',
                            ],
                            'counts' => [
                                'Usuários' => User::class,
                                'Notas de ideia' => IdeaNote::class,
                                'Auditoria' => AuditLog::class,
                                'Relatórios QA' => QaRun::class,
                            ],
                        ],
                    ),
                    self::command(
                        'ops.stack',
                        'probe',
                        'Stack (DB, cache, disco)',
                        'Ping na base, cache, storage, fila, mail e APP_KEY. Sem escrita de negócio.',
                        'operate',
                        null,
                        'ops',
                    ),
                    self::command(
                        'ops.migrations',
                        'probe',
                        'Migrations',
                        'Lista pendentes. Não corre migrate.',
                        'flow',
                        null,
                        'migrations',
                    ),
                    self::command(
                        'ops.google',
                        'probe',
                        'Google Agenda',
                        'Nível de integração (embed, OAuth, API). Sem chamar a API.',
                        'calendar',
                        null,
                        'google',
                    ),
                ],
            ],
            [
                'id' => 'email',
                'label' => 'E-mail',
                'icon' => 'mail',
                'description' => 'SMTP/IMAP, cliente Correio (só admin) e e-mail de teste. Convites de depoimento continuam só com link para copiar.',
                'commands' => [
                    self::command(
                        'mail.health',
                        'probe',
                        'Saúde do e-mail',
                        'Rotas e views do submódulo. Só leitura — não envia mensagem.',
                        'mail',
                        null,
                        'module',
                        [
                            'routes' => [
                                'admin.settings.mail',
                                'admin.settings.mail.update',
                                'admin.settings.mail.test',
                                'admin.settings.mail.inbox',
                                'admin.mailbox.index',
                                'admin.mailbox.compose',
                                'admin.mailbox.contacts',
                                'admin.mailbox.bulk',
                                'admin.mailbox.marker',
                                'admin.mailbox.label',
                                'admin.mailbox.link',
                            ],
                            'views' => [
                                'admin.settings.mail',
                                'mail.test',
                                'mail.crm',
                                'mail.crm-text',
                                'admin.mailbox.index',
                            ],
                        ],
                    ),
                    self::command(
                        'mail.config',
                        'probe',
                        'Configuração SMTP',
                        'Confere overlay, mailer e se a senha não vaza no snapshot. Só leitura.',
                        'settings',
                        null,
                        'mail.config',
                    ),
                ],
            ],
            [
                'id' => 'telegram',
                'label' => 'Telegram',
                'icon' => 'telegram',
                'description' => 'Probes reais no bot: ping, reenvio CRM e estado do webhook.',
                'commands' => [
                    self::command(
                        'telegram.ping',
                        'probe',
                        'Ping no bot',
                        'Envia uma mensagem de teste aos chats de notificação.',
                        'telegram',
                        null,
                        'telegram.ping',
                    ),
                    self::command(
                        'telegram.resend_last',
                        'probe',
                        'Reenviar último contato CRM',
                        'Dispara de novo a última mensagem do site pelo bot (WhatsApp + card).',
                        'message',
                        'Reenviar a última mensagem CRM pelo Telegram?',
                        'telegram.resend_last',
                    ),
                    self::command(
                        'telegram.webhook',
                        'probe',
                        'Estado do webhook',
                        'Consulta getMe e getWebhookInfo na API do Telegram.',
                        'integrations',
                        null,
                        'telegram.webhook',
                    ),
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return collect(self::groups())
            ->flatMap(fn (array $group) => collect($group['commands'])->pluck('id'))
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    public static function find(string $id): ?array
    {
        foreach (self::groups() as $group) {
            foreach ($group['commands'] as $command) {
                if ($command['id'] === $id) {
                    return $command;
                }
            }
        }

        return null;
    }

    public static function label(string $id): string
    {
        return (string) (self::find($id)['label'] ?? $id);
    }

    public static function isSuite(string $id): bool
    {
        return (self::find($id)['kind'] ?? '') === 'suite';
    }

    public static function groupId(string $commandId): ?string
    {
        foreach (self::groups() as $group) {
            foreach ($group['commands'] as $command) {
                if ($command['id'] === $commandId) {
                    return $group['id'];
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{label: string, route?: string, path?: string}>
     */
    public static function publicTargets(): array
    {
        return [
            ['label' => 'Health /up', 'path' => '/up'],
            ['label' => 'Home', 'route' => 'home'],
            ['label' => 'Privacidade', 'route' => 'privacy'],
            ['label' => 'Cookies', 'route' => 'cookies'],
            ['label' => 'Login', 'route' => 'login'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: string, kind: string, label: string, hint: string, icon: string, confirm: ?string, runner: string, payload: array<string, mixed>}
     */
    private static function command(
        string $id,
        string $kind,
        string $label,
        string $hint,
        string $icon,
        ?string $confirm = null,
        string $runner = 'custom',
        array $payload = [],
    ): array {
        return [
            'id' => $id,
            'kind' => $kind,
            'label' => $label,
            'hint' => $hint,
            'icon' => $icon,
            'confirm' => $confirm,
            'runner' => $runner,
            'payload' => $payload,
        ];
    }
}
