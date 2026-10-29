<?php

namespace App\Support;

use App\Services\SettingService;
use Illuminate\Support\Str;

final class MailBranding
{
    public const SIGNER = 'Jader Gabriel';

    public const LOCKUP = 'BURI-TI - Consultoria & Software';

    public static function personal(?string $name, ?string $email = null, ?string $phone = null, ?string $site = null): string
    {
        return self::signerName($name)."\n".self::LOCKUP;
    }

    /**
     * @return array{
     *     name: string,
     *     role: string,
     *     company: string,
     *     logo: string,
     *     site_url: string,
     *     site_host: string,
     *     channels: list<array{id: string, href: string, icon: string, label: string}>
     * }
     */
    public static function block(?string $custom = null, ?string $name = null): array
    {
        $lines = preg_split('/\R+/', trim((string) $custom)) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));

        return [
            'name' => self::signerName($lines[0] ?? null, $name),
            'role' => self::lockup($lines[1] ?? null),
            'company' => 'BURI-TI',
            'logo' => self::imageUrl('images/logo-buriti.png'),
            'site_url' => self::siteUrl(),
            'site_host' => self::siteHost(),
            'channels' => self::channels(),
        ];
    }

    public static function signerName(?string $custom = null, ?string $fromName = null): string
    {
        foreach ([$custom, $fromName] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && ! self::isCompanyName($candidate)) {
                return $candidate;
            }
        }

        return self::SIGNER;
    }

    public static function lockup(?string $custom = null): string
    {
        $custom = trim((string) $custom);
        if ($custom === '' || self::isCompanyName($custom) || self::isLegacyTagline($custom)) {
            return self::LOCKUP;
        }

        return $custom;
    }

    private static function isCompanyName(string $value): bool
    {
        $fold = Str::lower(Str::ascii($value));

        return in_array($fold, ['buriti', 'buri-ti', 'buri ti'], true);
    }

    private static function isLegacyTagline(string $value): bool
    {
        $fold = Str::lower(Str::ascii($value));

        return str_contains($fold, 'consultoria e software')
            || str_contains($fold, 'consultoria & software');
    }

    /**
     * @return list<array{id: string, href: string, icon: string, label: string}>
     */
    public static function channels(?SettingService $settings = null): array
    {
        $settings ??= app(SettingService::class);
        $phone = $settings->get('contact_phone');
        $whatsapp = $settings->get('contact_whatsapp') ?: $phone;
        $telegram = self::telegramHref(
            $settings->get('telegram_url'),
            $settings->get('telegram_handle'),
        );

        $channels = [];
        $tel = PhoneNumber::telHref($phone);
        if ($tel !== null) {
            $channels[] = [
                'id' => 'phone',
                'href' => $tel,
                'icon' => self::imageUrl('images/mail/phone.png'),
                'label' => 'Telefone',
            ];
        }
        $wa = WhatsAppLink::href($whatsapp);
        if ($wa !== null) {
            $channels[] = [
                'id' => 'whatsapp',
                'href' => $wa,
                'icon' => self::imageUrl('images/mail/whatsapp.png'),
                'label' => 'WhatsApp',
            ];
        }
        if ($telegram !== null) {
            $channels[] = [
                'id' => 'telegram',
                'href' => $telegram,
                'icon' => self::imageUrl('images/mail/telegram.png'),
                'label' => 'Telegram',
            ];
        }

        return $channels;
    }

    public static function telegramHref(?string $url, ?string $handle = null): ?string
    {
        $url = trim((string) $url);
        if (preg_match('#^https://t\.me/[A-Za-z0-9_]+#', $url) === 1) {
            return explode('?', $url, 2)[0];
        }

        $handle = ltrim(trim((string) $handle), '@');
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', $handle) === 1) {
            return 'https://t.me/'.$handle;
        }

        return null;
    }

    public static function imageUrl(string $path): string
    {
        return asset($path);
    }

    public static function legalNotice(?string $privacyUrl = null, ?string $privacyEmail = null): string
    {
        $privacyUrl = $privacyUrl ?: self::privacyUrl();
        $privacyEmail = $privacyEmail
            ?: (string) config('mail.from.address')
            ?: (string) config('buriti.contact.email')
            ?: 'jadergabriel8@gmail.com';

        return 'Esta mensagem e quaisquer anexos são de propriedade da BURI-TI e destinam-se exclusivamente ao destinatário. '
            .'Podem conter informação confidencial ou privilegiada. Se a recebeu por engano, elimine-a e avise-nos; '
            .'é vedado copiar, armazenar, divulgar ou utilizar o conteúdo.'
            ."\n\n"
            .'Os dados pessoais eventualmente tratados nesta comunicação observam a Lei nº 13.709/2018 (LGPD) e a Política de Privacidade da BURI-TI. '
            .'O titular pode exercer os seus direitos pelo e-mail '.$privacyEmail.' ou em '.$privacyUrl.'.';
    }

    public static function privacyUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/privacidade';
    }

    public static function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function siteHost(?string $url = null): string
    {
        $url = trim((string) ($url ?: self::siteUrl()));
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'buriti.dev.br';
    }
}
