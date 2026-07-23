<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramApiClient
{
    public function configured(): bool
    {
        return filled(config('services.telegram.bot_token'));
    }

    public function sendMessage(string|int $chatId, string $text, ?array $replyMarkup = null): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        try {
            $response = Http::timeout(12)
                ->asJson()
                ->post($this->endpoint('sendMessage'), $payload);

            if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                Log::warning('Telegram sendMessage failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage exception', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        $payload = [
            'url' => $url,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => false,
        ];

        if (filled($secretToken)) {
            $payload['secret_token'] = $secretToken;
        }

        $response = Http::timeout(15)->asJson()->post($this->endpoint('setWebhook'), $payload);

        return [
            'ok' => (bool) ($response->json('ok') ?? false),
            'description' => (string) ($response->json('description') ?? $response->body()),
            'result' => $response->json('result'),
        ];
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    public function getMe(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        $response = Http::timeout(12)->get($this->endpoint('getMe'));

        return [
            'ok' => (bool) ($response->json('ok') ?? false),
            'description' => (string) ($response->json('description') ?? ''),
            'result' => $response->json('result'),
        ];
    }

    private function endpoint(string $method): string
    {
        $token = (string) config('services.telegram.bot_token');

        return "https://api.telegram.org/bot{$token}/{$method}";
    }
}
