<?php

namespace App\Services\Telegram;

use App\Services\SettingService;
use Illuminate\Support\Str;

class TelegramShareCardService
{
    public function __construct(private SettingService $settings) {}

    /**
     * @return array{
     *     path: string,
     *     caption: string,
     *     reply_markup: array{inline_keyboard: list<list<array{text: string, url: string}>>},
     *     delete_after_send: bool
     * }
     */
    public function build(?string $forLabel = null): array
    {
        $forLabel = $this->normalizeLabel($forLabel);
        $path = $this->renderImage($forLabel);

        return [
            'path' => $path,
            'caption' => $this->caption($forLabel),
            'reply_markup' => [
                'inline_keyboard' => $this->buttons(),
            ],
            'delete_after_send' => true,
        ];
    }

    public function caption(?string $forLabel = null): string
    {
        $forLabel = $this->normalizeLabel($forLabel);
        $email = $this->contact('contact_email');
        $phone = $this->contact('contact_phone');
        $site = $this->siteUrl();

        $lines = array_filter([
            '🌿 <b>BURI-TI</b> — Tecnologia para Pessoas',
            $forLabel ? 'Card preparado para <b>'.$this->escape($forLabel).'</b>' : null,
            '',
            'Consultoria, software, BI e gestão de projetos — com entrega mensurável.',
            '',
            $email ? '✉️ '.$this->escape($email) : null,
            $phone ? '📞 '.$this->escape($phone) : null,
            '🌐 <a href="'.$this->escape($site).'">'.$this->escape($this->displayHost($site)).'</a>',
            '',
            '<i>Encaminhe este card ao cliente ou use os botões abaixo.</i>',
        ]);

        return implode("\n", $lines);
    }

    /** @return list<list<array{text: string, url: string}>> */
    public function buttons(): array
    {
        $site = $this->siteUrl();
        $contact = $site.'#contato';
        $whatsapp = $this->whatsappUrl();
        $linkedin = trim((string) ($this->settings->get('linkedin_url') ?? ''));

        $row1 = [
            ['text' => '🌐 Site', 'url' => $site],
        ];
        if ($whatsapp !== '') {
            $row1[] = ['text' => '💬 WhatsApp', 'url' => $whatsapp];
        }

        $row2 = [
            ['text' => '✉️ Pedir proposta', 'url' => $contact],
        ];
        if ($linkedin !== '') {
            $row2[] = ['text' => 'LinkedIn', 'url' => $linkedin];
        }

        return array_values(array_filter([$row1, $row2]));
    }

    private function renderImage(?string $forLabel): string
    {
        $width = 1080;
        $height = 608;
        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);

        $ink = imagecolorallocate($img, 10, 18, 28);
        $panel = imagecolorallocate($img, 16, 28, 42);
        $brand = imagecolorallocate($img, 199, 70, 52);
        $brandBright = imagecolorallocate($img, 226, 75, 53);
        $snow = imagecolorallocate($img, 238, 243, 248);
        $mist = imagecolorallocate($img, 148, 163, 184);
        $line = imagecolorallocate($img, 42, 58, 78);
        $blue = imagecolorallocatealpha($img, 26, 95, 158, 95);

        imagefilledrectangle($img, 0, 0, $width, $height, $ink);

        // Atmosphere blobs
        imagefilledellipse($img, 140, 90, 420, 320, $blue);
        imagefilledellipse($img, 980, 520, 480, 360, imagecolorallocatealpha($img, 199, 70, 52, 105));

        // Card panel
        imagefilledrectangle($img, 48, 48, $width - 48, $height - 48, $panel);
        imagerectangle($img, 48, 48, $width - 48, $height - 48, $line);

        // Brand accent bar
        imagefilledrectangle($img, 48, 48, 64, $height - 48, $brandBright);

        $bold = $this->fontPath(true);
        $regular = $this->fontPath(false);

        $this->text($img, 42, 92, 118, $snow, $bold, 'BURI-TI');
        $this->text($img, 18, 98, 168, $mist, $regular, 'TECNOLOGIA PARA PESSOAS');

        if ($forLabel) {
            $this->text($img, 20, 98, 220, $brandBright, $regular, 'Para: '.$forLabel);
            $y = 270;
        } else {
            $y = 240;
        }

        $this->text($img, 28, 98, $y, $snow, $bold, 'Infraestrutura de software,');
        $this->text($img, 28, 98, $y + 42, $snow, $bold, 'dados e BI para a operação.');

        $services = 'Consultoria · Desenvolvimento · Power BI · Projetos · Integrações';
        $this->text($img, 17, 98, $y + 110, $mist, $regular, $services);

        // Divider
        imagefilledrectangle($img, 98, $height - 168, $width - 98, $height - 167, $line);

        $email = $this->contact('contact_email') ?: '';
        $phone = $this->contact('contact_phone') ?: '';
        $host = $this->displayHost($this->siteUrl());

        $this->text($img, 18, 98, $height - 128, $snow, $regular, $email);
        $this->text($img, 18, 98, $height - 98, $mist, $regular, trim($phone.'  ·  '.$host));

        $logoPath = public_path('images/logo-buriti.png');
        if (is_file($logoPath)) {
            $logo = @imagecreatefrompng($logoPath);
            if ($logo !== false) {
                $lw = imagesx($logo);
                $lh = imagesy($logo);
                $target = 118;
                $scale = min($target / $lw, $target / $lh);
                $dw = (int) round($lw * $scale);
                $dh = (int) round($lh * $scale);
                imagecopyresampled(
                    $img,
                    $logo,
                    $width - 98 - $dw,
                    92,
                    0,
                    0,
                    $dw,
                    $dh,
                    $lw,
                    $lh
                );
                imagedestroy($logo);
            }
        }

        // Small brand chip
        imagefilledrectangle($img, $width - 220, $height - 132, $width - 98, $height - 92, $brand);
        $this->text($img, 16, $width - 204, $height - 106, $snow, $bold, 'COMPARTILHAR');

        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.'/telegram-card-'.Str::lower(Str::random(12)).'.png';
        imagepng($img, $path, 6);
        imagedestroy($img);

        return $path;
    }

    private function text($img, float $size, int $x, int $y, int $color, string $font, string $text): void
    {
        if ($font !== '' && function_exists('imagettftext')) {
            imagettftext($img, $size, 0, $x, $y, $color, $font, $text);

            return;
        }

        imagestring($img, 5, $x, max(0, $y - 14), $text, $color);
    }

    private function fontPath(bool $bold): string
    {
        $candidates = $bold
            ? [
                resource_path('fonts/DejaVuSans-Bold.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            ]
            : [
                resource_path('fonts/DejaVuSans.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return '';
    }

    private function siteUrl(): string
    {
        $configured = rtrim((string) config('app.url'), '/');
        if ($configured !== '') {
            return $configured;
        }

        return 'https://buriti.dev.br/public';
    }

    private function displayHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'buriti.dev.br';
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path !== '' ? $host.'/'.$path : $host;
    }

    private function whatsappUrl(): string
    {
        $raw = preg_replace('/\D+/', '', (string) ($this->settings->get('contact_whatsapp') ?? '')) ?: '';
        if ($raw === '') {
            return '';
        }

        $text = rawurlencode('Olá! Vi o card da BURI-TI e gostaria de falar sobre um projeto.');

        return 'https://wa.me/'.$raw.'?text='.$text;
    }

    private function contact(string $key): string
    {
        return trim((string) ($this->settings->get($key) ?? ''));
    }

    private function normalizeLabel(?string $label): ?string
    {
        $label = trim((string) $label);
        if ($label === '') {
            return null;
        }

        return Str::limit(preg_replace('/\s+/', ' ', $label) ?: $label, 48, '…');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
