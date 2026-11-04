<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Staff-entered link targets may only be http(s) URLs or site-relative paths.
 * Blade's escaping doesn't stop a `javascript:` scheme in an href, so this is
 * what prevents stored XSS through a link field (QA N4-01).
 */
class SafeLinkUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail(__('admin.banners.link_url_invalid'));
        }
    }

    public static function isValid(string $value): bool
    {
        // "//host" and "/\host" are rejected: browsers treat them as another site.
        return preg_match('#^(https?://\S+|/(?![/\\\\])\S*)$#iD', $value) === 1;
    }
}
