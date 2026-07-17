<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Carbon/Symfony ship no real translation data for `uz` — calling
 * `->translatedFormat()` with the app locale set to `uz` (this app's
 * default) silently falls back to Russian month names rather than
 * erroring, which is worse than it sounds: a Latin-script Uzbek page
 * suddenly showing Cyrillic text reads as broken, not just untranslated.
 * `ru` and `en` both have real Carbon translation data and work correctly
 * with `translatedFormat()` directly — only `uz` needs this manual map.
 */
class LocalizedDate
{
    /**
     * @var array<int, string>
     */
    private const UZ_MONTHS_SHORT = [
        1 => 'Yan', 2 => 'Fev', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Iyun',
        7 => 'Iyul', 8 => 'Avg', 9 => 'Sen', 10 => 'Okt', 11 => 'Noy', 12 => 'Dek',
    ];

    /**
     * "M j, Y"-equivalent — abbreviated month, day, year — in whichever of
     * the app's 3 locales is currently active.
     */
    public static function short(?Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        if (app()->getLocale() === 'uz') {
            return self::UZ_MONTHS_SHORT[(int) $date->format('n')].' '.$date->format('j, Y');
        }

        return $date->translatedFormat('M j, Y');
    }

    /**
     * "M j"-equivalent — abbreviated month + day, no year — for compact
     * contexts like chart axis labels.
     */
    public static function monthDay(?Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        if (app()->getLocale() === 'uz') {
            return self::UZ_MONTHS_SHORT[(int) $date->format('n')].' '.$date->format('j');
        }

        return $date->translatedFormat('M j');
    }
}
