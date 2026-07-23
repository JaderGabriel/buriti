<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, TelegramBotService $bot): Response
    {
        $expected = (string) config('services.telegram.webhook_secret');

        if ($expected === '' || ! hash_equals($expected, $secret)) {
            abort(404);
        }

        // Exige o header oficial do Telegram (secret_token do setWebhook).
        // O secret na URL sozinho não basta — evita abuso se o path vazar em logs.
        $headerSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if ($headerSecret === '' || ! hash_equals($expected, $headerSecret)) {
            abort(403);
        }

        if (! $bot->configured()) {
            return response('ok', 200);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $bot->handleUpdate($payload);

        return response('ok', 200);
    }
}
