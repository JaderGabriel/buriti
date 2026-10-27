<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Auth\TotpService;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_generated_secret_round_trips_current_code(): void
    {
        $totp = new TotpService;
        $secret = $totp->generateSecret();
        $at = 1_700_000_000;
        $code = $totp->at($secret, $at);

        $this->assertSame(6, strlen($code));
        $this->assertTrue($totp->verify($secret, $code, $at));
        $this->assertFalse($totp->verify($secret, '000000', $at));
        $this->assertTrue($totp->verify($secret, $code, $at + 29));
        $this->assertTrue($totp->verify($secret, $code, $at - 1));
        $this->assertFalse($totp->verify($secret, $code, $at + 60));
    }

    public function test_otpauth_uri_points_to_ios_passwords(): void
    {
        $user = new User(['email' => 'admin@buriti.test']);
        $totp = new TotpService;
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = $totp->otpauthUri($user, $secret);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('BURI-TI', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('period=30', $uri);
        $this->assertSame('JBSW Y3DP EHPK 3PXP', $totp->groupedSecret($secret));
    }
}
