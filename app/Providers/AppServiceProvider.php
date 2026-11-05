<?php

namespace App\Providers;

use App\Exceptions\SellerAccessDeniedException;
use App\Http\Middleware\EnsureAdminIsAuthenticated;
use App\Http\Middleware\EnsureSellerIsAuthenticated;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use App\Services\NavMenu;
use App\Services\TwoFactorService;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Real visitor IP behind a reverse proxy — config-driven so it
        // survives config:cache (env() is null outside config files then).
        if ($proxies = config('ribbon.trusted_proxies')) {
            TrustProxies::at(array_map('trim', explode(',', $proxies)));
        }

        // Makes the one sitewide Setting row available as $settings in the
        // storefront layout (tracking scripts, verification meta tags,
        // footer contact info, fallback SEO tags) without every individual
        // storefront page component having to pass it through ->layout().
        // Authorization gates for the admin/CMS side. `admin` mirrors
        // EnsureAdminIsAuthenticated (holds any role_user role),
        // `super-admin` mirrors EnsureUserIsSuperAdmin, and
        // `admin-permission` is the permission_role-backed check a
        // Moderator-style role will use (Super Admin always passes).
        // Typed `User` params make guests fail closed.
        // Every admin ability is denied until 2FA passes. Route middleware doesn't
        // run on Livewire's update endpoint, so this is what protects actions.
        Gate::before(function (User $user, string $ability) {
            if (in_array($ability, ['admin', 'super-admin', 'manage-subscriptions', 'admin-permission'], true)
                && app(TwoFactorService::class)->blocks($user)) {
                return false;
            }

            return null;
        });

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('super-admin', fn (User $user) => $user->isSuperAdmin());
        // Subscriptions / paid_until / payments: Super Admin only. The
        // Moderator must never see or change them.
        Gate::define('manage-subscriptions', fn (User $user) => $user->isSuperAdmin());
        Gate::define('admin-permission', fn (User $user, string $permission) => $user->hasAdminPermission($permission));

        // Seller side: `seller` = linked to an approved seller (mirrors
        // EnsureSellerIsAuthenticated), `seller-owner` = Owner of that
        // specific seller (employees, company profile, feed partners).
        Gate::define('seller', function (User $user) {
            try {
                $user->sellerOrFail();

                return true;
            } catch (SellerAccessDeniedException) {
                return false;
            }
        });
        Gate::define('seller-owner', fn (User $user, Seller $seller) => $user->isOwnerOf($seller));

        // Livewire update requests (POST /livewire/update) don't pass
        // through the original route's custom middleware; re-run these.
        Livewire::addPersistentMiddleware([
            EnsureAdminIsAuthenticated::class,
            EnsureSellerIsAuthenticated::class,
            EnsureUserIsSuperAdmin::class,
        ]);

        // Storefront navigation (spec 19): the cached mega-menu data and the
        // published header/footer CMS pages, supplied to the layout once so
        // no page component has to pass them. The per-request `active`
        // flag is added outside the cache.
        View::composer('layouts.storefront', function ($view) {
            $locale = app()->getLocale();
            $nav = app(NavMenu::class);

            $view->with('settings', Setting::current());
            $view->with('mega', NavMenu::markActive($nav->megaMenu($locale), $this->activeCategoryId($locale)));
            $view->with('menuPages', $nav->menuPages($locale));
        });
    }

    /**
     * Category the current storefront request belongs to: the catalog page's
     * own category, or the viewed product's category. Null elsewhere.
     */
    private function activeCategoryId(string $locale): ?int
    {
        $route = request()->route();

        if ($route === null) {
            return null;
        }

        if ($route->getName() === 'storefront.catalog.show') {
            $slug = (string) $route->parameter('categorySlug');

            return Category::query()
                ->where(fn ($q) => collect(config('ribbon.locales'))->each(fn ($l) => $q->orWhere("slug->{$l}", $slug)))
                ->value('id');
        }

        if ($route->getName() === 'storefront.products.show') {
            $slug = (string) $route->parameter('productSlug');

            return Product::query()
                ->where(fn ($q) => collect(config('ribbon.locales'))->each(fn ($l) => $q->orWhere("slug->{$l}", $slug)))
                ->value('category_id');
        }

        return null;
    }
}
