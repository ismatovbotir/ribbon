<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton — always exactly one row, fetched/created on demand via
 * current() rather than looked up by a route-bound ID anywhere (there is
 * no "settings list", just the one sitewide record Super Admin edits at
 * admin.settings.show).
 */
#[Fillable([
    'google_analytics_id',
    'yandex_metrica_id',
    'google_site_verification',
    'yandex_site_verification',
    'admin_phone',
    'admin_email',
    'default_meta_description',
    'default_og_image_path',
])]
class Setting extends Model
{
    /**
     * The one settings row, creating it with all-null columns on first
     * access if it doesn't exist yet — avoids needing a seeder for what is
     * purely optional, admin-entered config with no required values.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
