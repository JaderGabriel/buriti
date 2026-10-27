<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

final class AdminNavigation
{
    /**
     * @return list<array{
     *     id: string,
     *     route: string,
     *     label: string,
     *     icon: string,
     *     pattern: string|list<string>,
     *     badge?: int,
     *     items: list<array<string, mixed>>
     * }>
     */
    public static function modules(?User $user, int $unreadMessages = 0): array
    {
        if ($user?->is_admin !== true) {
            return [
                [
                    'id' => 'jirau',
                    'route' => 'jirau.home',
                    'label' => 'Jirau',
                    'icon' => 'jirau',
                    'pattern' => 'jirau.*',
                    'items' => [],
                ],
            ];
        }

        return [
            [
                'id' => 'home',
                'route' => 'admin.dashboard',
                'label' => 'Início',
                'icon' => 'dashboard',
                'pattern' => 'admin.dashboard',
                'items' => [
                    [
                        'route' => 'admin.dashboard',
                        'label' => 'Início',
                        'pattern' => 'admin.dashboard',
                        'icon' => 'dashboard',
                    ],
                ],
            ],
            [
                'id' => 'comercial',
                'route' => 'admin.messages.index',
                'label' => 'Comercial',
                'icon' => 'opportunity',
                'pattern' => ['admin.messages.*', 'admin.companies.*', 'admin.contacts.*', 'admin.opportunities.*'],
                'badge' => $unreadMessages > 0 ? $unreadMessages : null,
                'items' => [
                    [
                        'route' => 'admin.messages.index',
                        'label' => 'Mensagens',
                        'pattern' => 'admin.messages.*',
                        'icon' => 'message',
                        'badge' => $unreadMessages > 0 ? $unreadMessages : null,
                    ],
                    [
                        'route' => 'admin.companies.index',
                        'label' => 'Empresas',
                        'pattern' => 'admin.companies.*',
                        'icon' => 'company',
                    ],
                    [
                        'route' => 'admin.contacts.index',
                        'label' => 'Contatos',
                        'pattern' => 'admin.contacts.*',
                        'icon' => 'contact',
                    ],
                    [
                        'route' => 'admin.opportunities.index',
                        'label' => 'Oportunidades',
                        'pattern' => 'admin.opportunities.*',
                        'icon' => 'opportunity',
                    ],
                ],
            ],
            [
                'id' => 'entrega',
                'route' => 'admin.projects.index',
                'label' => 'Entrega',
                'icon' => 'project',
                'pattern' => ['admin.projects.*', 'admin.home-projects.*', 'admin.tasks.*'],
                'items' => [
                    [
                        'route' => 'admin.projects.index',
                        'label' => 'Projetos',
                        'pattern' => 'admin.projects.*',
                        'icon' => 'project',
                    ],
                    [
                        'route' => 'admin.home-projects.edit',
                        'label' => 'Site',
                        'pattern' => 'admin.home-projects.*',
                        'icon' => 'star',
                    ],
                    [
                        'route' => 'admin.tasks.index',
                        'label' => 'Agenda',
                        'pattern' => 'admin.tasks.*',
                        'icon' => 'task',
                    ],
                ],
            ],
            [
                'id' => 'jirau',
                'route' => 'jirau.home',
                'label' => 'Jirau',
                'icon' => 'jirau',
                'pattern' => 'jirau.*',
                'items' => [
                    [
                        'route' => 'jirau.home',
                        'label' => 'Quadros',
                        'pattern' => ['jirau.home', 'jirau.show', 'jirau.cards.*', 'jirau.demands.*', 'jirau.members.*', 'jirau.steps.*', 'jirau.status', 'jirau.update', 'jirau.boards.store', 'jirau.columns.*'],
                        'icon' => 'jirau',
                    ],
                    [
                        'route' => 'jirau.overview',
                        'label' => 'Quadro geral',
                        'pattern' => 'jirau.overview',
                        'icon' => 'flow',
                    ],
                    [
                        'route' => 'jirau.analytics',
                        'label' => 'Analítica',
                        'pattern' => ['jirau.analytics', 'jirau.analytics.show'],
                        'icon' => 'bi',
                    ],
                    [
                        'route' => 'jirau.staff',
                        'label' => 'Equipe',
                        'pattern' => ['jirau.staff', 'jirau.staff.store'],
                        'icon' => 'users',
                    ],
                ],
            ],
            [
                'id' => 'sistema',
                'route' => 'admin.settings.edit',
                'label' => 'Sistema',
                'icon' => 'settings',
                'pattern' => ['admin.users.*', 'admin.integrations.*', 'admin.settings.*', 'admin.roadmap.*'],
                'items' => [
                    [
                        'route' => 'admin.users.index',
                        'label' => 'Usuários',
                        'pattern' => 'admin.users.*',
                        'icon' => 'users',
                    ],
                    [
                        'route' => 'admin.integrations.edit',
                        'label' => 'Integrações',
                        'pattern' => 'admin.integrations.*',
                        'icon' => 'integrations',
                    ],
                    [
                        'route' => 'admin.settings.edit',
                        'label' => 'Configurações',
                        'pattern' => 'admin.settings.*',
                        'icon' => 'settings',
                    ],
                    [
                        'route' => 'admin.roadmap.show',
                        'label' => 'Roadmap',
                        'pattern' => 'admin.roadmap.*',
                        'icon' => 'roadmap',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $modules
     * @return array<string, mixed>|null
     */
    public static function current(array $modules): ?array
    {
        foreach ($modules as $module) {
            if (self::matches($module['pattern'] ?? '')) {
                return $module;
            }
        }

        return null;
    }

    public static function matches(string|array $pattern): bool
    {
        if ($pattern === [] || $pattern === '') {
            return false;
        }

        return is_array($pattern)
            ? request()->routeIs(...$pattern)
            : request()->routeIs($pattern);
    }

    /**
     * @return list<array{type: string, id: string, title: string, subtitle: string, url: string, group: string}>
     */
    public static function search(string $term, ?User $user = null): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $hits = [];

        foreach (self::modules($user) as $module) {
            $moduleId = (string) ($module['id'] ?? '');
            if (self::matchesTerm($term, $module['label'] ?? '', $moduleId, self::keywordsFor($moduleId))) {
                $hits[] = [
                    'type' => 'módulo',
                    'id' => 'module:'.$moduleId,
                    'title' => $module['label'],
                    'subtitle' => 'Módulo',
                    'url' => route($module['route']),
                    'group' => $moduleId,
                ];
            }

            foreach ($module['items'] ?? [] as $item) {
                $sameAsModule = ($item['route'] ?? null) === ($module['route'] ?? null)
                    && ($item['label'] ?? '') === ($module['label'] ?? '');
                if ($sameAsModule) {
                    continue;
                }

                $routeName = (string) ($item['route'] ?? '');
                if (! self::matchesTerm(
                    $term,
                    $item['label'] ?? '',
                    $module['label'] ?? '',
                    $routeName,
                    self::keywordsFor($routeName),
                    self::keywordsFor($moduleId)
                )) {
                    continue;
                }

                $hits[] = [
                    'type' => 'página',
                    'id' => 'page:'.($routeName !== '' ? $routeName : Str::slug((string) ($item['label'] ?? 'item'))),
                    'title' => $item['label'],
                    'subtitle' => $module['label'],
                    'url' => $item['url'] ?? route($item['route']),
                    'group' => $moduleId,
                ];
            }
        }

        return $hits;
    }

    private static function matchesTerm(string $term, string ...$parts): bool
    {
        $needle = self::fold($term);
        if ($needle === '') {
            return false;
        }

        return str_contains(self::fold(implode(' ', $parts)), $needle);
    }

    private static function fold(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }

    private static function keywordsFor(string $key): string
    {
        return [
            'home' => 'painel dashboard inicio',
            'comercial' => 'crm vendas leads comercial',
            'entrega' => 'projetos portfolio entrega',
            'jirau' => 'kanban agil board quadro',
            'sistema' => 'admin sistema',
            'admin.messages.index' => 'inbox email mail mensagens',
            'admin.companies.index' => 'company empresas clientes',
            'admin.contacts.index' => 'contactos telefone contatos',
            'admin.opportunities.index' => 'pipeline negocio oportunidades',
            'admin.projects.index' => 'portfolio projetos',
            'admin.home-projects.edit' => 'home vitrine site',
            'admin.tasks.index' => 'tarefas calendario calendar agenda',
            'jirau.home' => 'boards quadros',
            'jirau.overview' => 'overview geral',
            'jirau.analytics' => 'analytics bi analitica',
            'jirau.staff' => 'staff time equipe',
            'admin.users.index' => 'users usuarios',
            'admin.integrations.edit' => 'google telegram integracoes',
            'admin.settings.edit' => 'settings config configuracoes',
            'admin.roadmap.show' => 'roadmap produto ideias concorrentes pipedrive hubspot modulos',
        ][$key] ?? '';
    }
}
