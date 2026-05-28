<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace Plugin\Website\Support;

/**
 * 官网富文本清洗工具。
 *
 * 官网内容会被公开接口直接交给前端渲染，因此写入时只保留常用排版、图片、视频和表格标签，
 * 移除脚本、事件属性、iframe/object 与危险协议，避免公开页面出现 XSS 或不可控外链执行。
 */
final class RichText
{
    /** @var array<string, array<int, string>> */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'source' => ['src', 'type'],
        'video' => ['src', 'poster', 'width', 'height', 'preload', 'controls', 'muted', 'loop', 'playsinline'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    /** @var array<int, string> */
    private const ALLOWED_TAGS = [
        'a', 'blockquote', 'br', 'code', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 's', 'source', 'span', 'strong', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'u', 'ul', 'b', 'video',
    ];

    public static function sanitize(string $html, int $maxLength = 50000): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = mb_substr($html, 0, $maxLength);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="website-rich-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('website-rich-root');
        if (!$root instanceof \DOMElement) {
            return htmlspecialchars(self::plainText($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        self::sanitizeNode($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child) ?: '';
        }

        return mb_substr(trim($result), 0, $maxLength);
    }

    public static function plainText(string $html, int $maxLength = 500): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));

        return mb_substr($text, 0, $maxLength);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null;) {
            $next = $child->nextSibling;
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object'], true)) {
                        $child->parentNode?->removeChild($child);
                    } else {
                        self::unwrapNode($child);
                    }
                } else {
                    self::sanitizeAttributes($child, $tag);
                    self::sanitizeNode($child);
                }
            } else {
                self::sanitizeNode($child);
            }
            $child = $next;
        }
    }

    private static function unwrapNode(\DOMElement $node): void
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
    }

    private static function sanitizeAttributes(\DOMElement $node, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
        $remove = [];
        foreach ($node->attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);
            if (str_starts_with($name, 'on') || !in_array($name, $allowed, true)) {
                $remove[] = $attribute->name;
                continue;
            }
            if (in_array($name, ['href', 'src', 'poster'], true) && !self::isSafeUrl($value)) {
                $remove[] = $attribute->name;
            }
        }

        foreach ($remove as $name) {
            $node->removeAttribute($name);
        }

        if ($tag === 'a' && $node->hasAttribute('href')) {
            $node->setAttribute('target', '_blank');
            $node->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }
}
