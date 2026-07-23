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

    public function deleteMessage(string|int $chatId, int $messageId): bool
    {
        if (! $this->configured() || $messageId <= 0) {
            return false;
        }

        try {
            $response = Http::timeout(12)
                ->asJson()
                ->post($this->endpoint('deleteMessage'), [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);

            if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                Log::warning('Telegram deleteMessage failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram deleteMessage exception', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @param  array{inline_keyboard: list<list<array{text: string, url: string}>>}|null  $replyMarkup
     */
    public function sendPhoto(string|int $chatId, string $absolutePath, string $caption = '', ?array $replyMarkup = null): bool
    {
        if (! $this->configured() || ! is_file($absolutePath)) {
            return false;
        }

        try {
            $payload = [
                'chat_id' => (string) $chatId,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ];

            if ($replyMarkup !== null) {
                $payload['reply_markup'] = json_encode($replyMarkup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }

            $response = Http::timeout(30)
                ->attach('photo', file_get_contents($absolutePath), basename($absolutePath))
                ->post($this->endpoint('sendPhoto'), $payload);

            if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                Log::warning('Telegram sendPhoto failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram sendPhoto exception', ['message' => $e->getMessage()]);

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

        return $this->normalize($response);
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    public function deleteWebhook(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        $response = Http::timeout(12)->asJson()->post($this->endpoint('deleteWebhook'), [
            'drop_pending_updates' => false,
        ]);

        return $this->normalize($response);
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    public function getWebhookInfo(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        $response = Http::timeout(12)->get($this->endpoint('getWebhookInfo'));

        return $this->normalize($response);
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    public function getMe(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        $response = Http::timeout(12)->get($this->endpoint('getMe'));

        return $this->normalize($response);
    }

    /**
     * @param  list<array{command: string, description: string}>  $commands
     * @return array{ok: bool, description?: string, result?: mixed}
     */
    public function setMyCommands(array $commands): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        $response = Http::timeout(12)->asJson()->post($this->endpoint('setMyCommands'), [
            'commands' => $commands,
        ]);

        return $this->normalize($response);
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    public function setMyName(string $name): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        $response = Http::timeout(12)->asJson()->post($this->endpoint('setMyName'), [
            'name' => $name,
        ]);

        return $this->normalize($response);
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    public function setMyDescription(string $description): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        $response = Http::timeout(12)->asJson()->post($this->endpoint('setMyDescription'), [
            'description' => $description,
        ]);

        return $this->normalize($response);
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    public function setMyShortDescription(string $description): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        $response = Http::timeout(12)->asJson()->post($this->endpoint('setMyShortDescription'), [
            'short_description' => $description,
        ]);

        return $this->normalize($response);
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    public function setMyProfilePhoto(string $absolutePath): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'description' => 'TELEGRAM_BOT_TOKEN em falta.'];
        }

        if (! is_file($absolutePath)) {
            return ['ok' => false, 'description' => "Imagem não encontrada: {$absolutePath}"];
        }

        $photoMeta = json_encode([
            'type' => 'static',
            'photo' => 'attach://bot_photo',
        ], JSON_THROW_ON_ERROR);

        $response = Http::timeout(30)
            ->attach('bot_photo', file_get_contents($absolutePath), basename($absolutePath))
            ->post($this->endpoint('setMyProfilePhoto'), [
                'photo' => $photoMeta,
            ]);

        return $this->normalize($response);
    }

    /** @return array{ok: bool, description?: string, result?: mixed} */
    private function normalize(\Illuminate\Http\Client\Response $response): array
    {
        return [
            'ok' => (bool) ($response->json('ok') ?? false),
            'description' => (string) ($response->json('description') ?? $response->body()),
            'result' => $response->json('result'),
        ];
    }

    private function endpoint(string $method): string
    {
        $token = (string) config('services.telegram.bot_token');

        return "https://api.telegram.org/bot{$token}/{$method}";
    }
}
