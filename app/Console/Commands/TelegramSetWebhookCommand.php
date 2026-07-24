<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramApiClient;
use Illuminate\Console\Command;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
        {--url= : URL completa do webhook (default: APP_URL/webhooks/telegram/{secret})}
        {--drop-pending : Descarta updates pendentes ao registar o webhook}';

    protected $description = 'Registra o webhook do bot Telegram na API do BotFather';

    public function handle(TelegramApiClient $api): int
    {
        $secret = (string) config('services.telegram.webhook_secret');

        if (! $api->configured()) {
            $this->error('Defina TELEGRAM_BOT_TOKEN no .env');

            return self::FAILURE;
        }

        if ($secret === '') {
            $this->error('Defina TELEGRAM_WEBHOOK_SECRET no .env');

            return self::FAILURE;
        }

        $url = $this->option('url') ?: rtrim((string) config('app.url'), '/').'/webhooks/telegram/'.$secret;

        $this->info("Webhook URL: {$url}");

        $result = $api->setWebhook($url, $secret, (bool) $this->option('drop-pending'));

        if (! ($result['ok'] ?? false)) {
            $this->error($result['description'] ?? 'Falha ao registar webhook.');

            return self::FAILURE;
        }

        $this->info('Webhook registado com sucesso.');

        $me = $api->getMe();
        if (($me['ok'] ?? false) && is_array($me['result'] ?? null)) {
            $username = $me['result']['username'] ?? '?';
            $this->line("Bot: @{$username}");
        }

        $info = $api->getWebhookInfo();
        if (($info['ok'] ?? false) && is_array($info['result'] ?? null) && ! empty($info['result']['last_error_message'])) {
            $this->warn('Último erro webhook (pode ser antigo): '.$info['result']['last_error_message']);
        }

        return self::SUCCESS;
    }
}
