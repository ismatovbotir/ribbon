<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Builds the "Contents" list of a sanitized rich-text body: gives every <h2>
 * an anchor id and returns the list of [id, label]. Only adds an attribute,
 * so the sanitized markup is otherwise left exactly as saved.
 */
final class TableOfContents
{
    /**
     * @return array{html: string, items: list<array{id: string, label: string}>}
     */
    public static function build(string $html): array
    {
        $items = [];
        $used = [];

        $html = preg_replace_callback('#<h2(\s[^>]*)?>(.*?)</h2>#is', function (array $m) use (&$items, &$used) {
            $attributes = $m[1] ?? '';
            $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($label === '') {
                return $m[0];
            }

            // Keep a saved id only when it is a clean, unused anchor; otherwise replace it.
            $existing = preg_match('/\sid\s*=\s*(["\'])(.*?)\1/is', $attributes, $found) ? $found[2] : null;

            if ($existing !== null && preg_match('/^[A-Za-z][\w:.-]*$/', $existing) && ! isset($used[$existing])) {
                $id = $existing;
            } else {
                $attributes = preg_replace('/\sid\s*=\s*(["\']).*?\1/is', '', $attributes) ?? $attributes;
                $base = Str::slug($label) ?: 'section';
                $id = $base;
                for ($i = 2; isset($used[$id]); $i++) {
                    $id = $base.'-'.$i;
                }
                $attributes .= ' id="'.e($id).'"';
            }

            $used[$id] = true;
            $items[] = ['id' => $id, 'label' => $label];

            return '<h2'.$attributes.'>'.$m[2].'</h2>';
        }, $html) ?? $html;

        return ['html' => $html, 'items' => $items];
    }
}
