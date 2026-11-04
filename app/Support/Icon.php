<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Ribbon's single icon system (storefront, seller and admin panels).
 *
 * Base: IBM Carbon icons (Apache-2.0, resources/svg/icons/carbon/LICENSE),
 * a curated subset vendored as SVG. They are filled 32px shapes rendered with
 * fill="currentColor". Domain icons Carbon lacks (ribbon roll, label roll,
 * printhead, …) live in resources/svg/icons/custom: drawn on the same 32px
 * grid with 2px strokes and square caps so they read as one family.
 *
 * Components ask for a semantic name (e.g. "edit"), never a file. To add an
 * icon: copy the Carbon SVG into carbon/ (or draw one in custom/), then map
 * it here. Spec: .claude/docs/design/21-icon-system.md.
 */
final class Icon
{
    /**
     * Semantic name => file (relative to resources/svg/icons, no extension).
     * Old names (Heroicons and the first hand-drawn panel set) are kept as
     * aliases, so existing call sites keep working.
     *
     * @var array<string, string>
     */
    public const MAP = [
        // Navigation and objects
        'dashboard' => 'carbon/dashboard',
        'catalog' => 'carbon/catalog',
        'chart' => 'carbon/chart--column',
        'building' => 'carbon/building',
        'users' => 'carbon/user--multiple',
        'category' => 'carbon/category',
        'tag' => 'carbon/tag',
        'location' => 'carbon/location',
        'store' => 'carbon/store',
        'product' => 'carbon/product',
        'document' => 'carbon/document',
        'image' => 'carbon/image',
        'article' => 'carbon/blog',
        'page' => 'carbon/document--blank',
        'email' => 'carbon/email',
        'chat' => 'carbon/chat',
        'settings' => 'carbon/settings',
        'home' => 'carbon/home',
        'book' => 'carbon/book',
        'cube' => 'carbon/cube',
        'phone' => 'carbon/phone',
        'money' => 'carbon/money',
        'security' => 'carbon/security',
        'certificate' => 'carbon/certificate--check',
        'list-checked' => 'carbon/list--checked',
        'delivery' => 'carbon/delivery',
        'receipt' => 'carbon/receipt',
        'label' => 'carbon/label',
        'barcode' => 'carbon/barcode',
        'warning' => 'carbon/warning',
        'warning-alt' => 'carbon/warning--alt',
        'info' => 'carbon/information',
        'success' => 'carbon/checkmark--outline',
        'error' => 'carbon/error--outline',
        'time' => 'carbon/time',
        'pause' => 'carbon/pause',

        // Actions
        'view' => 'carbon/view',
        'review' => 'carbon/task--view',
        'edit' => 'carbon/edit',
        'copy' => 'carbon/copy',
        'add' => 'carbon/add',
        'close' => 'carbon/close',
        'search' => 'carbon/search',
        'menu' => 'carbon/menu',
        'filter' => 'carbon/settings--adjust',
        'funnel' => 'carbon/filter',
        'overflow' => 'carbon/overflow-menu--vertical',
        'sort' => 'carbon/caret--sort',
        'sort-asc' => 'carbon/caret--sort--up',
        'sort-desc' => 'carbon/caret--sort--down',
        'check' => 'carbon/checkmark',
        'reject' => 'carbon/close--outline',
        'block' => 'carbon/error',
        'delete' => 'carbon/trash-can',
        'user-remove' => 'carbon/user--minus',
        'user-add' => 'carbon/user--follow',
        'login' => 'carbon/login',
        'logout' => 'carbon/logout',
        'lock' => 'carbon/locked',
        'arrow-up' => 'carbon/arrow--up',
        'arrow-down' => 'carbon/arrow--down',
        'arrow-left' => 'carbon/arrow--left',
        'arrow-right' => 'carbon/arrow--right',
        'chevron-up' => 'carbon/chevron--up',
        'chevron-down' => 'carbon/chevron--down',
        'chevron-left' => 'carbon/chevron--left',
        'chevron-right' => 'carbon/chevron--right',

        // Product domain (category icons; custom unless Carbon has a good one)
        'ribbon-roll' => 'custom/ribbon-roll',
        'label-roll' => 'custom/label-roll',
        'printer' => 'carbon/printer',
        'printer-desktop' => 'custom/printer-desktop',
        'printer-industrial' => 'custom/printer-industrial',
        'printer-mobile' => 'custom/printer-mobile',
        'printhead' => 'custom/printhead',
        'scanner' => 'custom/scanner',
        'pda' => 'custom/pda',
        'receipt-roll' => 'custom/receipt-roll',
        'wristband' => 'custom/wristband',
        'software' => 'carbon/application',
        'accessories' => 'carbon/tool-kit',
    ];

    /** @var array<string, string> Legacy names => semantic names. */
    public const ALIASES = [
        // first panel set
        'table' => 'catalog', 'grid' => 'category', 'shield' => 'tag', 'map-pin' => 'location',
        'photo' => 'image', 'mail' => 'email', 'eye' => 'view', 'pencil' => 'edit',
        'duplicate' => 'copy', 'sliders' => 'filter', 'x-circle' => 'reject', 'trash' => 'delete',
        'user-minus' => 'user-remove', 'plus' => 'add',
        // storefront Heroicons
        'x' => 'close', 'clipboard-document-list' => 'list-checked', 'adjustments-horizontal' => 'filter',
        'shield-check' => 'security', 'check-badge' => 'certificate', 'document-text' => 'document',
        'banknotes' => 'money', 'chat-bubble-left-right' => 'chat', 'clipboard' => 'copy',
        'exclamation-circle' => 'warning', 'book-open' => 'book', 'newspaper' => 'article',
        'envelope' => 'email', 'building-storefront' => 'store', 'user-plus' => 'user-add',
    ];

    /**
     * Icons an admin can pick for a category (mega menu, seller panel).
     *
     * @var array<int, string>
     */
    public const CATEGORY_ICONS = [
        'ribbon-roll', 'label-roll', 'receipt-roll', 'wristband', 'printer-desktop',
        'printer-industrial', 'printer-mobile', 'printhead', 'scanner', 'pda', 'barcode',
        'software', 'accessories', 'product',
    ];

    /** @var array<string, string> */
    private static array $cache = [];

    public static function exists(string $name): bool
    {
        return isset(self::MAP[self::ALIASES[$name] ?? $name]);
    }

    /**
     * Inline SVG for $name. $attributes is an already-rendered attribute
     * string (class, x-bind, …) added to the root <svg>. Always decorative
     * (aria-hidden): pair every icon with visible text or an aria-label.
     * Unknown names render nothing rather than breaking the page.
     */
    public static function svg(string $name, string $attributes = ''): HtmlString
    {
        $file = self::MAP[self::ALIASES[$name] ?? $name] ?? null;

        if ($file === null) {
            report(new \InvalidArgumentException("Unknown icon [$name]."));

            return new HtmlString('');
        }

        $markup = self::$cache[$file] ??= self::load($file);

        return new HtmlString(preg_replace(
            '/^<svg\b/',
            '<svg '.trim($attributes).' aria-hidden="true" focusable="false"',
            $markup,
            1,
        ));
    }

    private static function load(string $file): string
    {
        $svg = trim((string) file_get_contents(resource_path("svg/icons/{$file}.svg")));
        $svg = preg_replace('/\s(width|height)="[^"]*"/', '', $svg, 2);

        // Carbon files are filled shapes with no fill set; custom files set
        // their own stroke styling on the root element.
        if (! preg_match('/^<svg\b[^>]*\b(fill|stroke)=/', $svg)) {
            $svg = preg_replace('/^<svg\b/', '<svg fill="currentColor"', $svg, 1);
        }

        return $svg;
    }
}
