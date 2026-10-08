<?php
/*
 * CityPortal   — a lightweight, privacy-first PHP engine for a local news portal: news, events, cinema, weather, online radio and an admin panel.
 * Copyright (c) 2026 PsyGioX — https://github.com/PsyGioX · https://psygiox-dev.vercel.app/
 * Project: https://github.com/PsyGioX/cityportalru
 */
declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Очистка HTML по белому списку. Всё, чего нет в списке, удаляется:
 * скрипты, обработчики событий, style, javascript:/data: ссылки, iframe, формы.
 */
final class Sanitizer
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'strong' => [], 'em' => [], 'u' => [], 's' => [], 'sup' => [], 'sub' => [], 'mark' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'hr' => [], 'code' => [], 'pre' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'title'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];
    private const RENAME = ['b' => 'strong', 'i' => 'em', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4', 'strike' => 's', 'del' => 's'];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math',
        'link', 'meta', 'base', 'noscript', 'template', 'canvas', 'audio', 'video', 'source', 'frame', 'frameset', 'applet', 'head', 'title'];
    private const BLOCK = ['p', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'table', 'figure', 'hr', 'pre', 'div'];

    public static function html(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }
        self::walk($body);
        self::wrapLoose($dom, $body);
        $out = '';
        foreach ($body->childNodes as $c) {
            $out .= $dom->saveHTML($c);
        }
        $out = preg_replace('~<p>(?:\s|&nbsp;|\x{00A0}|<br\s*/?>)*</p>~u', '', $out) ?? $out;
        return trim(preg_replace('~>\s*\n\s*<~', ">\n<", $out) ?? $out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if (!$child instanceof DOMElement) {
                $node->removeChild($child); // комментарии, PI, CDATA
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            self::walk($child);
            $tag = self::RENAME[$tag] ?? $tag;
            if ($tag !== strtolower($child->tagName)) {
                $child = self::rename($child, $tag);
            }
            if ($tag === 'div') {
                $hasBlock = false;
                foreach ($child->childNodes as $cc) {
                    if ($cc instanceof DOMElement && in_array(strtolower($cc->tagName), self::BLOCK, true)) {
                        $hasBlock = true;
                        break;
                    }
                }
                if (!$hasBlock && trim($child->textContent) !== '') {
                    $child = self::rename($child, 'p');
                    $tag = 'p';
                }
            }
            if (!isset(self::ALLOWED[$tag])) {
                self::unwrap($child);
                continue;
            }
            self::filterAttrs($child, $tag);
            if ($tag === 'a' && !$child->hasAttribute('href')) {
                self::unwrap($child);
            } elseif ($tag === 'img' && !$child->hasAttribute('src')) {
                $node->removeChild($child);
            }
        }
    }

    private static function rename(DOMElement $el, string $name): DOMElement
    {
        $new = $el->ownerDocument->createElement($name);
        while ($el->firstChild) {
            $new->appendChild($el->firstChild);
        }
        $el->parentNode->replaceChild($new, $el);
        return $new;
    }

    private static function unwrap(DOMElement $el): void
    {
        $p = $el->parentNode;
        while ($el->firstChild) {
            $p->insertBefore($el->firstChild, $el);
        }
        $p->removeChild($el);
    }

    private static function filterAttrs(DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (!in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);
                continue;
            }
            $val = trim($attr->value);
            switch ($name) {
                case 'href':
                    $clean = preg_replace('/[\x00-\x20\x7F]+/', '', $val) ?? '';
                    if (!preg_match('~^(https?://|mailto:|tel:|/(?!/)|#)~i', $clean)) {
                        $el->removeAttribute('href');
                    } else {
                        $el->setAttribute('href', $clean);
                    }
                    break;
                case 'src':
                    $clean = preg_replace('/[\x00-\x20\x7F]+/', '', $val) ?? '';
                    if (!preg_match('~^/(uploads|assets)/[A-Za-z0-9/_.\-]+$~', $clean)) {
                        $el->removeAttribute('src');
                    } else {
                        $el->setAttribute('src', $clean);
                    }
                    break;
                case 'target':
                    if ($val !== '_blank') {
                        $el->removeAttribute('target');
                    }
                    break;
                case 'width':
                case 'height':
                case 'colspan':
                case 'rowspan':
                    if (!ctype_digit($val)) {
                        $el->removeAttribute($name);
                    }
                    break;
                default:
                    $el->setAttribute($name, mb_substr($val, 0, 300));
            }
        }
        if ($tag === 'a') {
            if ($el->getAttribute('target') === '_blank') {
                $el->setAttribute('rel', 'noopener noreferrer');
            } else {
                $el->removeAttribute('rel');
            }
        }
        if ($tag === 'img') {
            // width/height из редактора (в том числе после растягивания мышью) отбрасываются:
            // подставляются настоящие размеры файла из медиатеки, поэтому пропорции не искажаются.
            $el->removeAttribute('width');
            $el->removeAttribute('height');
            if (preg_match('~^/uploads/(\d{4}/\d{2}/[a-f0-9]+)(?:-\d+)?\.(?:jpg|png|webp|gif)$~', $el->getAttribute('src'), $m)) {
                try {
                    $dim = \App\Core\DB::one('SELECT width, height FROM media WHERE path = ?', [$m[1]]);
                } catch (\Throwable) {
                    $dim = null;
                }
                if ($dim && $dim['width'] > 0 && $dim['height'] > 0) {
                    $el->setAttribute('width', (string) $dim['width']);
                    $el->setAttribute('height', (string) $dim['height']);
                }
            }
            $el->setAttribute('loading', 'lazy');
            if (!$el->hasAttribute('alt')) {
                $el->setAttribute('alt', '');
            }
        }
    }

    /** Текст и строчные элементы на верхнем уровне оборачиваются в абзацы. */
    private static function wrapLoose(DOMDocument $dom, DOMElement $body): void
    {
        $p = null;
        foreach (iterator_to_array($body->childNodes) as $c) {
            $isBlock = $c instanceof DOMElement && in_array(strtolower($c->tagName), [...self::BLOCK, 'h2', 'h3', 'h4', 'thead', 'tbody', 'tr'], true);
            if ($isBlock) {
                $p = null;
                continue;
            }
            if ($c instanceof DOMText && trim($c->textContent) === '') {
                if ($p === null) {
                    $body->removeChild($c);
                    continue;
                }
            }
            if ($p === null) {
                $p = $dom->createElement('p');
                $body->insertBefore($p, $c);
            }
            $p->appendChild($c);
        }
    }

    /** Чистый текст для поиска и описаний. */
    public static function plain(string $html): string
    {
        $t = preg_replace('~</(p|h[1-6]|li|div|blockquote|tr|figcaption)>|<br\s*/?>~i', '$0 ', $html) ?? $html;
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $t) ?? '');
    }

    public static function readingMinutes(string $text): int
    {
        return max(1, (int) ceil(count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []) / 180));
    }
}
