<?php

namespace Tests\Unit;

use App\Services\Telegram\TelegramApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TelegramApiClientTest extends TestCase
{
    public function test_send_message_exception_does_not_log_bot_token(): void
    {
        config(['services.telegram.bot_token' => '123456:TESTSECRETTOKEN']);
        Http::fake(function () {
            throw new ConnectionException(
                'cURL error 28 for https://api.telegram.org/bot123456:TESTSECRETTOKEN/sendMessage'
            );
        });
        Log::spy();

        $ok = app(TelegramApiClient::class)->sendMessage('1', 'oi');

        $this->assertFalse($ok);
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            $blob = $message.' '.json_encode($context);

            return str_contains($message, 'sendMessage exception')
                && ! str_contains($blob, 'TESTSECRETTOKEN')
                && str_contains($blob, '[redacted]');
        })->once();
    }
}
