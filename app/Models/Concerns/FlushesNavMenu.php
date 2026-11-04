<?php

namespace App\Models\Concerns;

use App\Services\NavMenu;

/**
 * Busts the cached storefront navigation (mega menu + menu pages) whenever
 * a model that feeds it is saved or deleted: Category, CategoryParameter,
 * CategoryParameterOption, Brand, Article, Product and Page. Eloquent boots
 * this through the `boot{TraitName}` convention.
 */
trait FlushesNavMenu
{
    protected static function bootFlushesNavMenu(): void
    {
        static::saved(fn () => NavMenu::flush());
        static::deleted(fn () => NavMenu::flush());
    }
}
