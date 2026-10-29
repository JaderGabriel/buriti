<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

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
