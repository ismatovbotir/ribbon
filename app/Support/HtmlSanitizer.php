<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Minimal allow-by-default / deny-specific sanitizer for admin-authored rich
 * text (Article::body). Admins are a trusted-but-not-fully-trusted actor
 * (CMS staff, not Super Admin only) editing HTML that later renders
 * unescaped on the public storefront via {!! $body !!}, so this exists to
 * stop stored XSS from a compromised or careless admin account rather than
 * to fully whitelist Trix's output — it strips the dangerous element types
 * and attribute patterns and otherwise leaves Trix's markup untouched.
 */
class HtmlSanitizer
{
    private const DANGEROUS_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta'];

    private const URL_ATTRIBUTES = ['href', 'xlink:href', 'src', 'action', 'formaction', 'poster', 'background'];

    /**
     * Relative URLs and an allow-list of schemes only (QA N4-11). Browsers
     * ignore tabs, newlines and leading control characters inside a scheme,
     * so those are removed before the scheme is read.
     */
    private static function isSafeUrl(string $attribute, string $value): bool
    {
        $url = preg_replace('/[\x00-\x20\x7F]+/', '', $value);

        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $match)) {
            return true;
        }

        if (in_array(strtolower($match[1]), ['http', 'https', 'mailto', 'tel'], true)) {
            return true;
        }

        return $attribute === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp);#i', $url) === 1;
    }

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8"?><div>'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $xpath = new DOMXPath($document);

        foreach (self::DANGEROUS_TAGS as $tag) {
            foreach (iterator_to_array($document->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach (iterator_to_array($xpath->query('//*')) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $value = trim($attribute->nodeValue ?? '');

                $isEventHandler = str_starts_with($name, 'on');
                $isUnsafeUrl = in_array($name, self::URL_ATTRIBUTES, true)
                    && ! self::isSafeUrl($name, $value);

                if ($isEventHandler || $isUnsafeUrl) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }
        }

        $wrapper = $document->getElementsByTagName('div')->item(0);
        $inner = '';

        foreach (iterator_to_array($wrapper->childNodes) as $child) {
            $inner .= $document->saveHTML($child);
        }

        return trim($inner);
    }
}
