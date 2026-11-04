<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Loose international phone check shared by every public phone field
 * (/request, /contact, seller registration). Buyers are CIS-wide, so this
 * deliberately doesn't enforce a +998 format. It only allows phone
 * punctuation (+, spaces, dashes, dots, parentheses) and 9–15 digits (E.164
 * maximum). That's enough to stop free text being saved as a lead, and to
 * stop it dodging the digits-only per-phone rate limit.
 */
class PhoneNumber implements ValidationRule
{
    public const MIN_DIGITS = 9;

    public const MAX_DIGITS = 15;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail(__('storefront.validation.phone_invalid'));
        }
    }

    public static function isValid(string $value): bool
    {
        $value = trim($value);

        if (! preg_match('/^\+?[\d\s().-]+$/', $value)) {
            return false;
        }

        $digits = strlen(self::digits($value));

        return $digits >= self::MIN_DIGITS && $digits <= self::MAX_DIGITS;
    }

    public static function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }
}
