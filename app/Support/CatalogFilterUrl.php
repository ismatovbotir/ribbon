<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\CategoryParameterOption;

/**
 * Builds catalog URLs in exactly the query-string shape
 * App\Livewire\Storefront\Catalog\Show reads through its #[Url] properties,
 * so mega-menu shortcuts and the filter sidebar share state:
 *
 *   f[{parameterId}][]  => $selected   (select option ids)
 *   min[{parameterId}]  => $numMin     (number filters)
 *   max[{parameterId}]  => $numMax
 *   brand[]             => $brands     (brand ids)
 *   sort                => $sort
 *
 * Arrays are serialised with http_build_query (indexed form,
 * f[12][0]=34), the same form Livewire itself writes into the address bar.
 * Such URLs are noindex + canonical-to-clean-category on the page itself.
 * Keep the keys here in sync with Catalog\Show's #[Url(as: ...)] names.
 */
class CatalogFilterUrl
{
    /**
     * Category URL for a locale with no filters.
     */
    public static function category(Category $category, ?string $locale = null): string
    {
        return self::build($category, [], $locale);
    }

    /**
     * Shortcut for one parameter value: a CategoryParameterOption (or its
     * id) for select parameters, or a number for `number` parameters
     * (filters min = max = value).
     */
    public static function for(Category $category, CategoryParameter $parameter, CategoryParameterOption|int|float|string $value, ?string $locale = null): string
    {
        if ($value instanceof CategoryParameterOption) {
            return self::option($category, $parameter, $value->id, $locale);
        }

        if ($parameter->type === 'number') {
            return self::number($category, $parameter, $value, $value, $locale);
        }

        return self::option($category, $parameter, (int) $value, $locale);
    }

    public static function option(Category $category, CategoryParameter|int $parameter, CategoryParameterOption|int $option, ?string $locale = null): string
    {
        $parameterId = $parameter instanceof CategoryParameter ? $parameter->id : $parameter;
        $optionId = $option instanceof CategoryParameterOption ? $option->id : $option;

        return self::build($category, ['f' => [$parameterId => [(string) $optionId]]], $locale);
    }

    public static function number(Category $category, CategoryParameter|int $parameter, int|float|string|null $min, int|float|string|null $max, ?string $locale = null): string
    {
        $parameterId = $parameter instanceof CategoryParameter ? $parameter->id : $parameter;
        $query = [];

        if ($min !== null && $min !== '') {
            $query['min'] = [$parameterId => self::numberString($min)];
        }

        if ($max !== null && $max !== '') {
            $query['max'] = [$parameterId => self::numberString($max)];
        }

        return self::build($category, $query, $locale);
    }

    public static function brand(Category $category, Brand|int $brand, ?string $locale = null): string
    {
        $brandId = $brand instanceof Brand ? $brand->id : $brand;

        return self::build($category, ['brand' => [(string) $brandId]], $locale);
    }

    /**
     * @param  array<string, mixed>  $query  raw #[Url] keys (f, min, max, brand, sort)
     */
    public static function build(Category $category, array $query = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $default = config('ribbon.locales')[0];
        $slug = $category->slug[$locale] ?? ($category->slug[$default] ?? '');

        $url = route('storefront.catalog.show', ['categorySlug' => $slug]);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * "110.000" (decimal(12,3) from the DB) -> "110"; "2.5" stays "2.5".
     */
    private static function numberString(int|float|string $value): string
    {
        $string = is_string($value) ? $value : (string) $value;

        if (! is_numeric($string)) {
            return $string;
        }

        return str_contains($string, '.') ? rtrim(rtrim($string, '0'), '.') : $string;
    }
}
