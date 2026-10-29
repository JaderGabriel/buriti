<?php

namespace App\Support;

final class MailBranding
{
    public static function personal(?string $name, ?string $email, ?string $phone = null, ?string $site = null): string
    {
        $name = trim((string) $name);
        $email = trim((string) $email);
        $phone = trim((string) $phone);
        $site = self::siteHost($site);

        $lines = array_values(array_filter([
            $name !== '' ? $name : 'BURI-TI',
            'BURI-TI · consultoria e software',
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
            $site,
        ]));

        return implode("\n", $lines);
    }

    public static function legalNotice(?string $privacyUrl = null, ?string $privacyEmail = null): string
    {
        $privacyUrl = $privacyUrl ?: self::privacyUrl();
        $privacyEmail = $privacyEmail ?: 'contato@buriti.dev.br';

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
