<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Http\Request;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use RuntimeException;

class PasskeyService
{
    public const REGISTER_SESSION = 'passkey.register.challenge';

    public const LOGIN_SESSION = 'passkey.login.challenge';

    public const MAX_PER_USER = 8;

    public function registrationOptions(User $user, Request $request): array
    {
        if ($user->webauthnCredentials()->count() >= self::MAX_PER_USER) {
            throw new RuntimeException('Limite de 8 chaves de acesso por conta.');
        }

        $webAuthn = $this->client($request);
        $exclude = $user->webauthnCredentials()
            ->pluck('credential_id')
            ->map(fn (string $id) => $this->b64urlDecode($id))
            ->all();

        $args = $webAuthn->getCreateArgs(
            (string) $user->id,
            $user->email,
            $user->name,
            60,
            true,
            true,
            false,
            $exclude,
        );

        $request->session()->put(self::REGISTER_SESSION, $this->b64urlEncode($webAuthn->getChallenge()->getBinaryString()));

        return $this->encodeOptions($args);
    }

    public function register(User $user, Request $request, array $payload): WebauthnCredential
    {
        $challenge = $this->pullChallenge($request, self::REGISTER_SESSION);
        $webAuthn = $this->client($request);

        try {
            $data = $webAuthn->processCreate(
                $this->b64urlDecode((string) ($payload['clientDataJSON'] ?? '')),
                $this->b64urlDecode((string) ($payload['attestationObject'] ?? '')),
                $challenge,
                true,
                true,
                false,
                false,
            );
        } catch (WebAuthnException $exception) {
            throw new RuntimeException('Não foi possível guardar a chave de acesso. Tente de novo no Safari.');
        }

        $credentialId = $this->b64urlEncode((string) $data->credentialId);

        if (WebauthnCredential::query()->where('credential_id', $credentialId)->exists()) {
            throw new RuntimeException('Esta chave de acesso já está vinculada.');
        }

        return $user->webauthnCredentials()->create([
            'credential_id' => $credentialId,
            'public_key' => (string) $data->credentialPublicKey,
            'sign_count' => (int) ($data->signatureCounter ?? 0),
            'name' => $this->deviceName($request, $payload),
            'aaguid' => filled($data->AAGUID ?? null) ? bin2hex((string) $data->AAGUID) : null,
        ]);
    }

    public function assertionOptions(Request $request): array
    {
        $webAuthn = $this->client($request);
        $args = $webAuthn->getGetArgs([], 60, true, true, true, true, true, true);
        $request->session()->put(self::LOGIN_SESSION, $this->b64urlEncode($webAuthn->getChallenge()->getBinaryString()));

        return $this->encodeOptions($args);
    }

    public function assert(Request $request, array $payload): User
    {
        $challenge = $this->pullChallenge($request, self::LOGIN_SESSION);
        $credentialId = (string) ($payload['id'] ?? $payload['rawId'] ?? '');
        $credential = WebauthnCredential::query()->where('credential_id', $credentialId)->first();

        if (! $credential) {
            throw new RuntimeException('Chave de acesso não reconhecida.');
        }

        /** @var User $user */
        $user = $credential->user;

        if (! $user || $user->is_active === false) {
            throw new RuntimeException('Não foi possível entrar com a chave de acesso.');
        }

        $userHandle = (string) ($payload['userHandle'] ?? '');
        if ($userHandle !== '' && $this->b64urlDecode($userHandle) !== (string) $user->id && $userHandle !== (string) $user->id) {
            throw new RuntimeException('Chave de acesso não reconhecida.');
        }

        $webAuthn = $this->client($request);

        try {
            $webAuthn->processGet(
                $this->b64urlDecode((string) ($payload['clientDataJSON'] ?? '')),
                $this->b64urlDecode((string) ($payload['authenticatorData'] ?? '')),
                $this->b64urlDecode((string) ($payload['signature'] ?? '')),
                $credential->public_key,
                $challenge,
                $credential->sign_count > 0 ? $credential->sign_count : null,
                true,
                true,
            );
        } catch (WebAuthnException $exception) {
            throw new RuntimeException('Não foi possível validar a chave de acesso.');
        }

        $nextCount = $webAuthn->getSignatureCounter();
        $credential->forceFill([
            'sign_count' => is_int($nextCount) ? $nextCount : $credential->sign_count,
            'last_used_at' => now(),
        ])->save();

        return $user;
    }

    public function supportedOn(Request $request): bool
    {
        $host = $this->rpId($request);

        if ($host === 'localhost') {
            return true;
        }

        return $request->secure()
            || $request->getScheme() === 'https'
            || $request->headers->get('X-Forwarded-Proto') === 'https';
    }

    private function client(?Request $request = null): WebAuthn
    {
        return new WebAuthn(
            (string) config('buriti.name', 'BURI-TI'),
            $this->rpId($request),
            ['none', 'packed', 'apple', 'android-key', 'android-safetynet'],
            true,
        );
    }

    private function rpId(?Request $request = null): string
    {
        foreach ([$request?->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)] as $host) {
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        return 'localhost';
    }

    private function encodeOptions(object $args): array
    {
        try {
            $encoded = json_decode(json_encode($args, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Não foi possível iniciar a chave de acesso.', 0, $exception);
        }

        if (! is_array($encoded) || ! isset($encoded['publicKey']) || ! is_array($encoded['publicKey'])) {
            throw new RuntimeException('Não foi possível iniciar a chave de acesso.');
        }

        return $encoded;
    }

    private function pullChallenge(Request $request, string $key): string
    {
        $stored = $request->session()->pull($key);

        if (! is_string($stored) || $stored === '') {
            throw new RuntimeException('A verificação expirou. Tente de novo.');
        }

        return $this->b64urlDecode($stored);
    }

    private function deviceName(Request $request, array $payload): string
    {
        $given = trim((string) ($payload['name'] ?? ''));
        if ($given !== '') {
            return mb_substr($given, 0, 80);
        }

        $agent = (string) $request->userAgent();
        if (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad')) {
            return 'iPhone / iPad';
        }
        if (str_contains($agent, 'Macintosh')) {
            return 'Mac';
        }
        if (str_contains($agent, 'Android')) {
            return 'Android';
        }

        return 'Chave de acesso';
    }

    private function b64urlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            throw new RuntimeException('Dados da chave de acesso inválidos.');
        }

        return $decoded;
    }
}
