<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramApiClient;
use Illuminate\Console\Command;

class TelegramConfigureCommand extends Command
{
    protected $signature = 'telegram:configure
        {--photo= : Caminho absoluto da foto de perfil (default: public/images/logo-buriti.png)}
        {--webhook-url= : URL HTTPS do webhook (se omitido, usa APP_URL)}
        {--skip-webhook : Não regista webhook}';

    protected $description = 'Configura nome, descrição, comandos, foto e webhook do bot BURI-TI';

    public function handle(TelegramApiClient $api): int
    {
        if (! $api->configured()) {
            $this->error('Defina TELEGRAM_BOT_TOKEN no .env');

            return self::FAILURE;
        }

        $me = $api->getMe();
        if (! ($me['ok'] ?? false)) {
            $this->error('Token inválido: '.($me['description'] ?? 'erro desconhecido'));

            return self::FAILURE;
        }

        $username = $me['result']['username'] ?? '?';
        $this->info("Bot autenticado: @{$username}");

        $steps = [
            'Nome' => $api->setMyName('BURI-TI CRM'),
            'Descrição' => $api->setMyDescription(
                "Assistente operacional da BURI-TI.\n\n".
                "Crie contatos, oportunidades, projetos e tarefas pelo Telegram.\n".
                "Receba alertas das mensagens do formulário do site buriti.dev.br.\n\n".
                "Comandos: /ajuda"
            ),
            'Descrição curta' => $api->setMyShortDescription(
                'CRM BURI-TI: contatos, oportunidades, projetos, tarefas e alertas do site.'
            ),
            'Comandos' => $api->setMyCommands([
                ['command' => 'ajuda', 'description' => 'Lista de comandos do CRM'],
                ['command' => 'id', 'description' => 'Mostra o Chat ID deste chat'],
                ['command' => 'status', 'description' => 'Resumo operacional do CRM'],
                ['command' => 'contato', 'description' => 'Criar/atualizar contato'],
                ['command' => 'oportunidade', 'description' => 'Criar oportunidade'],
                ['command' => 'projeto', 'description' => 'Criar projeto'],
                ['command' => 'tarefa', 'description' => 'Criar tarefa'],
            ]),
        ];

        foreach ($steps as $label => $result) {
            if ($result['ok'] ?? false) {
                $this->line("✓ {$label}");
            } else {
                $this->warn("✗ {$label}: ".($result['description'] ?? 'falhou'));
            }
        }

        $photo = $this->option('photo') ?: public_path('images/bot-avatar-buriti.png');
        if (! is_file($photo)) {
            $photo = public_path('images/logo-buriti.png');
        }
        $photoResult = $api->setMyProfilePhoto($photo);
        if ($photoResult['ok'] ?? false) {
            $this->line('✓ Foto de perfil (avatar claro BURI-TI)');
        } else {
            $this->warn('✗ Foto de perfil: '.($photoResult['description'] ?? 'falhou'));
            $this->line('  Alternativa: @BotFather → /setuserpic');
        }

        if (! $this->option('skip-webhook')) {
            $secret = (string) config('services.telegram.webhook_secret');
            if ($secret === '') {
                $this->warn('TELEGRAM_WEBHOOK_SECRET em falta — webhook não registado.');
            } else {
                $url = $this->option('webhook-url')
                    ?: rtrim((string) config('app.url'), '/').'/webhooks/telegram/'.$secret;

                if (! str_starts_with($url, 'https://')) {
                    $this->warn("Webhook exige HTTPS público. URL atual: {$url}");
                    $this->line('Use: php artisan telegram:configure --webhook-url=https://buriti.dev.br/public/webhooks/telegram/'.$secret);
                } else {
                    $hook = $api->setWebhook($url, $secret);
                    if ($hook['ok'] ?? false) {
                        $this->line("✓ Webhook: {$url}");
                    } else {
                        $this->warn('✗ Webhook: '.($hook['description'] ?? 'falhou'));
                    }
                }
            }
        }

        $info = $api->getWebhookInfo();
        if (($info['ok'] ?? false) && is_array($info['result'] ?? null)) {
            $this->newLine();
            $this->line('Webhook atual: '.($info['result']['url'] ?: '(vazio)'));
            if (! empty($info['result']['last_error_message'])) {
                $this->warn('Último erro webhook: '.$info['result']['last_error_message']);
            }
        }

        $this->newLine();
        $this->info('Próximos passos:');
        $this->line('1. Abra o bot no Telegram e envie /id');
        $this->line('2. Cole o Chat ID em Admin → Integrações → Telegram');
        $this->line('3. Defina o chat de notificação do formulário');

        return self::SUCCESS;
    }
}
