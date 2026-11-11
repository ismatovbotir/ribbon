<?php

namespace App\Services\Bot;

/** Text helpers for the rule-based bot: matching-friendly normalisation and PII masking. */
final class BotText
{
    /** Lowercase, one apostrophe form (Uzbek o', g'), letters and digits only, single spaces. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(['’', 'ʻ', 'ʼ', '`', '´', '‘'], "'", $text);
        $text = str_replace('ё', 'е', $text);
        $text = preg_replace("/[^\p{L}\p{N}']+/u", ' ', $text) ?? '';
        $text = preg_replace("/(?<!\p{L})'|'(?!\p{L})/u", ' ', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** @return list<string> */
    public static function tokens(string $text): array
    {
        $normalized = self::normalize($text);

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    /** Hide phone numbers, emails and @usernames before a buyer's question is stored for staff. */
    public static function mask(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[email]', $text) ?? '';
        $text = preg_replace('/\+?\d[\d\s().\-_\/]{5,}\d/u', '[phone]', $text) ?? '';
        $text = preg_replace('/(?<![\w.@])@\w{4,}/u', '[telegram]', $text) ?? '';

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, 255);
    }

    /**
     * Does one typed word match one keyword word? Keywords of 3 letters or
     * fewer must match exactly. Longer ones match on a shared start, so word
     * endings do not matter: 4 letters share 3 ("цена" ~ "цены"), 5 share 4
     * ("price" ~ "prices"), 6+ all but the last 2 ("доставка" ~ "доставку").
     */
    public static function wordMatches(string $typed, string $keyword): bool
    {
        if ($typed === $keyword) {
            return true;
        }

        $length = mb_strlen($keyword);

        if ($length <= 3) {
            return false;
        }

        $stem = match (true) {
            $length === 4 => 3,
            $length === 5 => 4,
            default => $length - 2,
        };

        return mb_strlen($typed) >= $stem && mb_substr($typed, 0, $stem) === mb_substr($keyword, 0, $stem);
    }
}
