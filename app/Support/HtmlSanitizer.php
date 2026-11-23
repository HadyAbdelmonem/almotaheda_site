<?php

namespace App\Support;

/**
 * Minimal allow-list HTML sanitizer for admin-authored rich text
 * (spec §9: only admin rich text is rendered unescaped, and only
 * after sanitization). Strips scripts, event handlers, javascript:
 * URLs and any non-allow-listed tag/attribute.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><a><h2><h3><h4><blockquote><span><div><table><thead><tbody><tr><th><td><img>';

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }
        // Remove dangerous elements together with their contents first;
        // strip_tags alone would keep a script's inner text behind.
        $clean = preg_replace('#<(script|style|iframe|object|embed|form)[^>]*>.*?</\\1>#is', '', $html) ?? '';
        $clean = strip_tags($clean, self::ALLOWED_TAGS);
        // Remove event handlers and style attributes.
        $clean = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean) ?? '';
        // Neutralize javascript:/data: URLs in href/src.
        $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript):[^"\']*\2/i', '$1="#"', $clean) ?? '';
        // Drop any remaining attributes except href/title on <a> and src/alt on <img>.
        $clean = preg_replace_callback('/<(\w+)((?:\s+[^>]*)?)>/', function (array $m) {
            $tag = strtolower($m[1]);
            $attrs = $m[2] ?? '';
            $kept = '';
            if ($tag === 'a' && preg_match('/href\s*=\s*"([^"]*)"/i', $attrs, $am)) {
                $kept .= ' href="'.e($am[1]).'" rel="noopener"';
            }
            if ($tag === 'img') {
                if (preg_match('/src\s*=\s*"([^"]*)"/i', $attrs, $am)) {
                    $kept .= ' src="'.e($am[1]).'"';
                }
                if (preg_match('/alt\s*=\s*"([^"]*)"/i', $attrs, $am)) {
                    $kept .= ' alt="'.e($am[1]).'"';
                }
            }

            return '<'.$tag.$kept.'>';
        }, $clean) ?? '';

        return $clean;
    }
}
