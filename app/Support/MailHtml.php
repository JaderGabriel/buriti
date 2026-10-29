<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

final class MailHtml
{
    /** @var list<string> */
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'hr', 'h2', 'h3', 'div', 'span'];

    /** @var list<string> */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'noscript'];

    public static function prepare(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (! preg_match('/<[a-z][\s\S]*>/i', $raw)) {
            return self::fromPlain($raw);
        }

        return self::sanitize($raw);
    }

    public static function fromPlain(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $html = '';
        $quote = [];
        $plain = [];

        $flushPlain = function () use (&$html, &$plain): void {
            if ($plain === []) {
                return;
            }
            $chunk = trim(implode("\n", $plain));
            $plain = [];
            if ($chunk === '') {
                $html .= '<p><br></p>';

                return;
            }
            $html .= '<p>'.nl2br(e($chunk), false).'</p>';
        };

        $flushQuote = function () use (&$html, &$quote): void {
            if ($quote === []) {
                return;
            }
            $inner = array_map(function (string $line): string {
                $line = preg_replace('/^>\s?/', '', $line) ?? $line;

                return e($line);
            }, $quote);
            $quote = [];
            $html .= '<blockquote>'.implode('<br>', $inner).'</blockquote>';
        };

        foreach ($lines as $line) {
            if (str_starts_with($line, '>')) {
                $flushPlain();
                $quote[] = $line;

                continue;
            }
            $flushQuote();
            $plain[] = $line;
        }
        $flushQuote();
        $flushPlain();

        return $html !== '' ? $html : '<p><br></p>';
    }

    public static function quoteBlock(string $intro, string $text): string
    {
        $intro = trim($intro);
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($intro === '' && $text === '') {
            return '';
        }

        $parts = [];
        if ($intro !== '') {
            $parts[] = '<p class="mail-quote__meta" style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.45;color:#8a96a3;font-weight:400;font-style:normal;">'.e($intro).'</p>';
        }
        if ($text !== '') {
            $parts[] = '<p class="mail-quote__text" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:#6a7787;font-weight:400;font-style:normal;">'.nl2br(e($text), false).'</p>';
        }

        return '<blockquote class="mail-quote" style="margin:18px 0 0;padding:4px 0 2px 14px;border-left:2px solid #c5d0dc;color:#6a7787;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;font-weight:400;font-style:normal;">'
            .implode('', $parts)
            .'</blockquote>';
    }

    public static function withQuote(string $body, string $quote): string
    {
        $body = self::prepare($body);
        $quote = trim($quote) === '' ? '' : self::sanitize($quote);
        if ($quote === '') {
            return $body;
        }
        if ($body === '' || self::isBlank($body)) {
            return $quote;
        }

        return $body.$quote;
    }

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div id="mail-html-root">'.$html.'</div>';
        $loaded = @$document->loadHTML('<?xml encoding="UTF-8">'.$wrapped, LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($loaded !== true) {
            return self::fromPlain(strip_tags($html));
        }

        $root = (new DOMXPath($document))->query('//*[@id="mail-html-root"]')->item(0);
        if (! $root instanceof DOMElement) {
            return self::fromPlain(strip_tags($html));
        }

        self::scrub($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }
        $out = trim($out);

        return $out !== '' ? $out : self::fromPlain(strip_tags($html));
    }

    public static function toText(string $html): string
    {
        $prepared = self::prepare($html);
        $prepared = preg_replace('/<br\s*\/?>/i', "\n", $prepared) ?? $prepared;
        $prepared = preg_replace('/<\/p>/i', "\n\n", $prepared) ?? $prepared;
        $prepared = preg_replace('/<\/(li|h2|h3|blockquote)>/i', "\n", $prepared) ?? $prepared;
        $prepared = preg_replace('/<li[^>]*>/i', '- ', $prepared) ?? $prepared;
        $text = html_entity_decode(strip_tags($prepared), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    public static function isBlank(string $html): bool
    {
        return self::toText($html) === '';
    }

    /**
     * Separa o corpo visível da assinatura e das citações, para previews (Telegram, inbox).
     *
     * @return array{body: string, signature: string}
     */
    public static function splitPreview(string $htmlOrText): array
    {
        $raw = trim($htmlOrText);
        if ($raw === '') {
            return ['body' => '', 'signature' => ''];
        }

        $bodyHtml = $raw;
        $signatureHtml = '';
        if (preg_match('/<[a-z][\s\S]*>/i', $raw) === 1) {
            [$bodyHtml, $signatureHtml] = self::splitHtmlPreview($raw);
        }

        $body = self::stripQuotedPlain(self::previewText($bodyHtml));
        $signature = self::compactSignature(self::previewText($signatureHtml));
        [$body, $textSignature] = self::splitTextSignature($body);
        if ($signature === '') {
            $signature = self::compactSignature($textSignature);
        }

        $body = self::stripLegalNotice($body);

        return [
            'body' => trim($body),
            'signature' => $signature,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitHtmlPreview(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div id="mail-html-root">'.$html.'</div>';
        $loaded = @$document->loadHTML('<?xml encoding="UTF-8">'.$wrapped, LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($loaded !== true) {
            return [$html, ''];
        }

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="mail-html-root"]')->item(0);
        if (! $root instanceof DOMElement) {
            return [$html, ''];
        }

        foreach (self::matchingNodes($xpath, $root, 'quote') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $signatureHtml = '';
        foreach (self::matchingNodes($xpath, $root, 'signature') as $node) {
            $signatureHtml .= $document->saveHTML($node);
            $node->parentNode?->removeChild($node);
        }

        if ($signatureHtml === '') {
            $signatureHtml = self::extractTrailingHrSignature($document, $xpath, $root);
        }

        $bodyHtml = '';
        foreach ($root->childNodes as $child) {
            $bodyHtml .= $document->saveHTML($child);
        }

        return [trim($bodyHtml), trim($signatureHtml)];
    }

    /**
     * @return list<DOMElement>
     */
    private static function matchingNodes(DOMXPath $xpath, DOMElement $root, string $kind): array
    {
        $nodes = [];
        foreach ($xpath->query('.//*', $root) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if ($kind === 'quote' && self::isQuoteNode($node)) {
                $nodes[] = $node;
            }
            if ($kind === 'signature' && self::isSignatureNode($node)) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private static function isQuoteNode(DOMElement $node): bool
    {
        $tag = strtolower($node->tagName);
        $class = strtolower(trim($node->getAttribute('class')));
        $id = strtolower(trim($node->getAttribute('id')));

        if ($tag === 'blockquote' && strtolower(trim($node->getAttribute('type'))) === 'cite') {
            return true;
        }

        return preg_match('/(?:^|\s)(?:gmail_quote|yahoo_quoted|mail-quote(?:__[a-z]+)?)\b/', $class) === 1
            || in_array($id, ['divrplyfwdmsg', 'appendonsend'], true);
    }

    private static function isSignatureNode(DOMElement $node): bool
    {
        $class = strtolower(trim($node->getAttribute('class')));
        $id = strtolower(trim($node->getAttribute('id')));

        return preg_match('/(?:^|\s)(?:gmail_signature|moz-signature|apple-mail-signature|x-signature|mail-signature)\b/', $class) === 1
            || preg_match('/(?:^|\s)signature(?:\s|$)/', $class) === 1
            || in_array($id, ['signature', 'applemailsignature'], true);
    }

    private static function extractTrailingHrSignature(DOMDocument $document, DOMXPath $xpath, DOMElement $root): string
    {
        $hrs = [];
        foreach ($xpath->query('.//hr', $root) as $node) {
            if ($node instanceof DOMElement) {
                $hrs[] = $node;
            }
        }
        if ($hrs === []) {
            return '';
        }

        $hr = $hrs[array_key_last($hrs)];
        $after = '';
        $nodes = [];
        $sibling = $hr->nextSibling;
        while ($sibling) {
            $after .= $document->saveHTML($sibling);
            $nodes[] = $sibling;
            $sibling = $sibling->nextSibling;
        }

        $text = self::previewText($after);
        if ($text === '' || mb_strlen($text) > 400 || ! self::looksLikeSignature($text)) {
            return '';
        }

        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }
        $hr->parentNode?->removeChild($hr);

        return trim($after);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitTextSignature(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return ['', ''];
        }

        $patterns = [
            '/\n-- \n/',
            '/\n--\n/',
            '/\n_{3,}\n/',
            '/\nEsta mensagem e quaisquer anexos\b/u',
            '/\n(?:Enviado do meu|Sent from my|Get Outlook for)\b/iu',
            '/\n(?:On .+ wrote:|Em .+ escreveu:)\s*(?:\n|$)/u',
            '/\n(?:From|De):\s.+\n(?:Sent|Enviado):/u',
        ];

        $cut = null;
        $kind = 'signature';
        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            $offset = (int) $match[0][1];
            if ($cut === null || $offset < $cut) {
                $cut = $offset;
                $kind = in_array($index, [3, 5, 6], true) ? 'discard' : 'signature';
            }
        }

        $branded = self::brandedSignatureOffset($text);
        if ($branded !== null && ($cut === null || $branded < $cut)) {
            $cut = $branded;
            $kind = 'signature';
        }

        if ($cut === null) {
            return [$text, ''];
        }

        $body = trim(substr($text, 0, $cut));
        $rest = trim(substr($text, $cut));
        $rest = preg_replace('/^(?:-- ?|_{3,})\s*/', '', $rest) ?? $rest;
        $rest = trim($rest);

        if ($kind === 'discard' || $body === '') {
            return [$body !== '' ? $body : $text, ''];
        }

        return [$body, $rest];
    }

    private static function brandedSignatureOffset(string $text): ?int
    {
        $signer = preg_quote(MailBranding::SIGNER, '/');
        $lockup = preg_quote(MailBranding::LOCKUP, '/');
        if (preg_match('/\n'.$signer.'\n+'.$lockup.'/u', $text, $match, PREG_OFFSET_CAPTURE) === 1) {
            return (int) $match[0][1];
        }

        return null;
    }

    private static function previewText(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (preg_match('/<[a-z][\s\S]*>/i', $html) !== 1) {
            $text = str_replace(["\r\n", "\r"], "\n", $html);
            $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
            $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

            return trim($text);
        }
        $html = preg_replace('/<img\b[^>]*>/i', '', $html) ?? $html;

        return self::toText($html);
    }

    private static function stripLegalNotice(string $text): string
    {
        $cut = preg_split('/\nEsta mensagem e quaisquer anexos\b/u', $text, 2);
        $text = trim((string) ($cut[0] ?? $text));
        $text = preg_replace('/\nPolítica de Privacidade\s*$/u', '', $text) ?? $text;

        return trim($text);
    }

    private static function stripQuotedPlain(string $text): string
    {
        $lines = preg_split('/\R/', str_replace(["\r\n", "\r"], "\n", $text)) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            if (preg_match('/^>/', $line) === 1) {
                continue;
            }
            $kept[] = $line;
        }
        $out = trim(implode("\n", $kept));
        $out = preg_replace("/\n{3,}/", "\n\n", $out) ?? $out;

        return trim($out);
    }

    private static function compactSignature(string $text): string
    {
        $text = self::stripLegalNotice($text);
        if ($text === '') {
            return '';
        }

        $drop = [
            'telefone',
            'whatsapp',
            'telegram',
            'politica de privacidade',
            'política de privacidade',
        ];
        $keep = [];
        foreach (preg_split('/\R+/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line === '--' || $line === '-- ' || str_starts_with($line, '>')) {
                continue;
            }
            $fold = Str::lower(Str::ascii($line));
            if (in_array($fold, $drop, true)) {
                continue;
            }
            if (str_starts_with($fold, 'esta mensagem e quaisquer anexos')) {
                break;
            }
            $keep[] = $line;
            if (count($keep) >= 4) {
                break;
            }
        }

        return implode("\n", $keep);
    }

    private static function looksLikeSignature(string $text): bool
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $text) ?: []), fn (string $line) => $line !== ''));
        if ($lines === [] || count($lines) > 8) {
            return false;
        }

        $longest = max(array_map('mb_strlen', $lines));

        return $longest <= 120;
    }

    private static function scrub(DOMNode $node): void
    {
        $remove = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                self::scrub($child);
                if (in_array($tag, self::DROP, true) || ! in_array($tag, self::ALLOWED, true)) {
                    $remove[] = $child;

                    continue;
                }
                self::scrubAttributes($child, $tag);
                if ($tag === 'img' && ! $child->hasAttribute('src')) {
                    $remove[] = $child;
                }
            }
        }
        foreach ($remove as $child) {
            $tag = $child instanceof DOMElement ? strtolower($child->tagName) : '';
            if (! in_array($tag, self::DROP, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
            }
            $node->removeChild($child);
        }
    }

    private static function scrubAttributes(DOMElement $el, string $tag): void
    {
        $keep = [];
        if ($tag === 'a') {
            $href = trim($el->getAttribute('href'));
            if (preg_match('#^(https?:|mailto:)#i', $href) === 1) {
                $keep['href'] = $href;
            }
        }
        if (in_array($tag, ['blockquote', 'p', 'div'], true)) {
            $class = trim($el->getAttribute('class'));
            if (preg_match('/^mail-quote(?:__[a-z]+)?$/', $class) === 1) {
                $keep['class'] = $class;
                $style = trim($el->getAttribute('style'));
                if ($style !== '' && preg_match('/^[-a-z0-9:#%.,;\s]+$/i', $style) === 1 && ! str_contains(strtolower($style), 'url(')) {
                    $keep['style'] = $style;
                }
            }
        }
        if ($tag === 'img') {
            $src = trim($el->getAttribute('src'));
            $cid = trim($el->getAttribute('data-cid'));
            if (preg_match('/^cid:[a-zA-Z0-9._-]+$/', $src) === 1) {
                $keep['src'] = $src;
            } elseif (preg_match('/^[a-zA-Z0-9._-]+$/', $cid) === 1) {
                $keep['src'] = 'cid:'.$cid;
            }
            $alt = trim($el->getAttribute('alt'));
            if ($alt !== '') {
                $keep['alt'] = mb_substr($alt, 0, 120);
            }
        }

        while ($el->attributes->length > 0) {
            $el->removeAttribute($el->attributes->item(0)?->nodeName ?? '');
        }
        foreach ($keep as $name => $value) {
            $el->setAttribute($name, $value);
        }
        if ($tag === 'a' && ! $el->hasAttribute('href')) {
            $el->removeAttribute('href');
        }
    }
}
