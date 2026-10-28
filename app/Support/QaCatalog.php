<?php

namespace App\Support;

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
                'description' => 'PHPUnit isolado (SQLite em memória). Não mexe na base de produção.',
                'commands' => [
                    self::command(
                        'suite.phpunit',
                        'suite',
                        'Rodar suíte completa',
                        './bin/test — cerca de 15–30 s neste servidor.',
                        'diagnose',
                        'Correr a suíte PHPUnit agora? Pode levar até 30 segundos.',
                    ),
                ],
            ],
            [
                'id' => 'telegram',
                'label' => 'Telegram',
                'description' => 'Probes reais no bot: ping, reenvio CRM e estado do webhook.',
                'commands' => [
                    self::command(
                        'telegram.ping',
                        'probe',
                        'Ping no bot',
                        'Envia uma mensagem de teste aos chats de notificação.',
                        'telegram',
                    ),
                    self::command(
                        'telegram.resend_last',
                        'probe',
                        'Reenviar último contato CRM',
                        'Dispara de novo a última mensagem do site pelo bot (WhatsApp + card).',
                        'message',
                        'Reenviar a última mensagem CRM pelo Telegram?',
                    ),
                    self::command(
                        'telegram.webhook',
                        'probe',
                        'Estado do webhook',
                        'Consulta getMe e getWebhookInfo na API do Telegram.',
                        'integrations',
                    ),
                ],
            ],
            [
                'id' => 'screens',
                'label' => 'Telas',
                'description' => 'GET nas páginas públicas: saúde, home e login.',
                'commands' => [
                    self::command(
                        'health.up',
                        'probe',
                        'Health /up',
                        'GET na rota de saúde da aplicação.',
                        'operate',
                    ),
                    self::command(
                        'health.home',
                        'probe',
                        'Página inicial',
                        'GET na home pública.',
                        'globe',
                    ),
                    self::command(
                        'health.login',
                        'probe',
                        'Tela de login',
                        'GET na página de entrada do admin.',
                        'admin',
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

    /**
     * @return array{id: string, kind: string, label: string, hint: string, icon: string, confirm: ?string}
     */
    private static function command(
        string $id,
        string $kind,
        string $label,
        string $hint,
        string $icon,
        ?string $confirm = null,
    ): array {
        return [
            'id' => $id,
            'kind' => $kind,
            'label' => $label,
            'hint' => $hint,
            'icon' => $icon,
            'confirm' => $confirm,
        ];
    }
}
