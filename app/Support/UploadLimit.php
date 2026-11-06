<?php

namespace App\Support;

/** The largest file this server's PHP settings will accept. */
class UploadLimit
{
    public static function bytes(): int
    {
        return min(self::toBytes((string) ini_get('upload_max_filesize')), self::toBytes((string) ini_get('post_max_size')));
    }

    public static function label(): string
    {
        return round(self::bytes() / 1048576, 1).' MB';
    }

    private static function toBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr(trim($value), -1))) {
            'G' => $number * 1073741824,
            'M' => $number * 1048576,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
