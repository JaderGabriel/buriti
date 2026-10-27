<?php

namespace App\Support;

use App\Models\User;

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
                'pattern' => ['admin.users.*', 'admin.integrations.*', 'admin.settings.*'],
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
}
