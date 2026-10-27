<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function fakeTelegramApi(): void
    {
        config([
            'services.telegram.bot_token' => '123456:TESTTOKEN',
            'services.telegram.bot_username' => 'BuritiCrmBot',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    protected function lastTelegramTwoFactorCode(): string
    {
        $code = null;

        foreach (Http::recorded() as [$request]) {
            $text = (string) ($request->data()['text'] ?? '');
            if (preg_match('/<code>(\d{6})<\/code>/', $text, $match) === 1) {
                $code = $match[1];
            }
        }

        $this->assertNotEmpty($code, 'Nenhum código 2FA foi enviado ao Telegram.');

        return (string) $code;
    }
}
