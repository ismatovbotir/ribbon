<?php

use App\Livewire\Admin\Articles\Form as AdminArticlesForm;
use App\Livewire\Admin\Articles\Index as AdminArticlesIndex;
use App\Livewire\Admin\Banners\Form as AdminBannersForm;
use App\Livewire\Admin\Banners\Index as AdminBannersIndex;
use App\Livewire\Admin\Brands\Index as AdminBrandsIndex;
use App\Livewire\Admin\Categories\Index as CategoriesIndex;
use App\Livewire\Admin\Categories\Show as CategoriesShow;
use App\Livewire\Admin\Geography\Cities\Index as AdminGeographyCitiesIndex;
use App\Livewire\Admin\Geography\Countries\Index as AdminGeographyCountriesIndex;
use App\Livewire\Admin\Geography\Regions\Index as AdminGeographyRegionsIndex;
use App\Livewire\Admin\Offers\Index as AdminOffersIndex;
use App\Livewire\Admin\Offers\Show as AdminOffersShow;
use App\Livewire\Admin\Products\Index as AdminProductsIndex;
use App\Livewire\Admin\Products\Show as AdminProductsShow;
use App\Livewire\Admin\Sellers\Index as AdminSellersIndex;
use App\Livewire\Admin\Sellers\Show as AdminSellersShow;
use App\Livewire\Admin\Settings\Show as AdminSettingsShow;
use App\Livewire\Auth\Login;
use App\Livewire\Sellers\Analytics\Show as SellerAnalyticsShow;
use App\Livewire\Sellers\Dashboard as SellerDashboard;
use App\Livewire\Sellers\Employees\Index as SellerEmployeesIndex;
use App\Livewire\Sellers\Products\Create as SellerProductsCreate;
use App\Livewire\Sellers\Products\Edit as SellerProductsEdit;
use App\Livewire\Sellers\Products\Index as SellerProductsIndex;
use App\Livewire\Sellers\Profile\Index as SellerProfileIndex;
use App\Livewire\Sellers\Register as SellerRegister;
use App\Livewire\Storefront\Articles\Index as StorefrontArticlesIndex;
use App\Livewire\Storefront\Articles\Show as StorefrontArticlesShow;
use App\Livewire\Storefront\Catalog\Show as StorefrontCatalogShow;
use App\Livewire\Storefront\Home as StorefrontHome;
use App\Livewire\Storefront\OfferRequest\Show as StorefrontOfferRequestShow;
use App\Livewire\Storefront\Products\Show as StorefrontProductsShow;
use App\Livewire\Storefront\Search as StorefrontSearch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Buyer storefront — public, unauthenticated (buyers never register/log in,
// see CLAUDE.md), no auth middleware. `{categorySlug}` is a plain string,
// not Laravel's implicit route-model-binding: Category.slug is a JSON
// column keyed by locale, so it can't bind against a single unique column —
// Storefront\Catalog\Show::mount() resolves it manually. Deliberately named
// `categorySlug`, not `category`, so it doesn't collide with the
// component's own `public Category $category` property — Livewire's
// full-page-component implicit route binding matches route parameter names
// against public property names/types, and would otherwise try to bind
// this raw string against the Category model itself and 404/error before
// mount() ever runs. See docs/design/09 (layout shell) and 11 (catalog
// filters).
Route::get('/', StorefrontHome::class)->name('storefront.home');

Route::prefix('catalog')->name('storefront.catalog.')->group(function () {
    Route::get('/{categorySlug}', StorefrontCatalogShow::class)->name('show');
});

// Product detail — same JSON-per-locale-slug + Livewire route-model-binding
// gotcha as catalog's {categorySlug} above, resolved manually in
// Storefront\Products\Show::mount() (parameter named `productSlug`, not
// `product`, so it doesn't collide with that component's own `public
// Product $product` property). Path matches the literal `/products/{slug}`
// example doc 10 (product card) and doc 12 (this page) both use.
Route::prefix('products')->name('storefront.products.')->group(function () {
    Route::get('/{productSlug}', StorefrontProductsShow::class)->name('show');
});

// Educational content (history, ribbon types, use cases, technical
// explainers) — admin-authored via Admin\Articles, public/unauthenticated
// like the rest of the storefront. /create-style ordering isn't a concern
// here (no {articleSlug} could ever literally be the word "articles").
Route::prefix('articles')->name('storefront.articles.')->group(function () {
    Route::get('/', StorefrontArticlesIndex::class)->name('index');
    Route::get('/{articleSlug}', StorefrontArticlesShow::class)->name('show');
});

// Global cross-category search — `q` matches the header search form's
// `name="q"` input (layouts/storefront.blade.php) and Storefront\Home's
// SearchAction JSON-LD (`/search?q={search_term_string}`) exactly, so both
// existing integration points work unchanged. See docs/design/11's
// cross-category-views scope cut (category-only filter, no per-parameter
// faceted sidebar) and Storefront\Search for the matching implementation.
Route::get('/search', StorefrontSearch::class)->name('storefront.search');

// The buyer's running Commercial Offer request selection — review the
// items added via Storefront\Products\Show::addToRequest() (see
// OfferSelectionService), then submit contact details. Public,
// unauthenticated like the rest of the storefront: the session itself is
// the buyer's "cart", not an account.
Route::get('/request', StorefrontOfferRequestShow::class)->name('storefront.offer-request.show');

// Public seller registration — no login/session system exists yet in this
// app, and this flow doesn't add one. It only submits an application
// (Seller::register()); the applicant is left on a static "under review"
// confirmation, not signed in.
Route::get('/sellers/register', SellerRegister::class)->name('sellers.register');

// Unified login — session-based (`web` guard), same `User` model/table for
// both admin/CMS staff and sellers, so there's a single login route rather
// than one per actor type. Login::login() resolves which actor the
// authenticated user actually is (admin role vs. approved Seller link) and
// redirects accordingly — see App\Livewire\Auth\Login. Logout is likewise
// shared: it's actor-agnostic (just tears down the session), so both the
// admin and seller panels post to this one route.
Route::get('/login', Login::class)->name('login');

Route::post('/logout', function () {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

// Seller dashboard — the real seller-panel shell (layouts.seller), gated
// behind `seller.auth` (see EnsureSellerIsAuthenticated).
Route::prefix('seller')->name('seller.')->group(function () {
    Route::get('/dashboard', SellerDashboard::class)
        ->middleware('seller.auth')
        ->name('dashboard');

    // A seller's own product catalog — scoped to their own products only
    // (see Index::render()/Edit::mount()). /create is registered before
    // /{product}, same reasoning as admin/banners's /create vs /{banner}
    // ordering above: otherwise "create" would be swallowed by the
    // {product} route-model-binding parameter.
    Route::prefix('products')->name('products.')->middleware('seller.auth')->group(function () {
        Route::get('/', SellerProductsIndex::class)->name('index');
        Route::get('/create', SellerProductsCreate::class)->name('create');
        Route::get('/{product}', SellerProductsEdit::class)->name('edit');
    });

    // Product performance stats (views, search appearances, traffic
    // source) — read-only, viewable by both Owner and Employee like
    // /seller/profile, not just the Owner.
    Route::get('/analytics', SellerAnalyticsShow::class)
        ->middleware('seller.auth')
        ->name('analytics');

    // Owner-only team management — Employees::mount() enforces the
    // authorization (403, not a redirect) on top of `seller.auth`'s
    // "logged in and linked to an approved seller" check.
    Route::get('/employees', SellerEmployeesIndex::class)
        ->middleware('seller.auth')
        ->name('employees');

    // Company profile (currently just the logo) — viewable by both Owner
    // and Employee (seeing the company's own logo isn't a privileged
    // action, unlike /employees above), but only Owner-editable; see
    // Sellers\Profile\Index's isOwner()/mutating-method guards.
    Route::get('/profile', SellerProfileIndex::class)
        ->middleware('seller.auth')
        ->name('profile');
});

// Admin/CMS — Super Admin category/category-parameter management and
// seller-application review. Gated behind `admin.auth` (see
// EnsureAdminIsAuthenticated): logged in AND holds an admin role via
// `role_user`. RBAC beyond the binary "has an admin role" check (i.e.
// per-permission gating via `permissions`/`permission_role`) is still
// unbuilt — see CLAUDE.md, the non-Super-Admin role list is unconfirmed.
Route::prefix('admin')->name('admin.')->middleware('admin.auth')->group(function () {
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', CategoriesIndex::class)->name('index');
        Route::get('/{category}', CategoriesShow::class)->name('show');
    });

    Route::prefix('brands')->name('brands.')->group(function () {
        Route::get('/', AdminBrandsIndex::class)->name('index');
    });

    // Country -> Region -> City geography tree — genuinely nested (unlike
    // Categories' deliberate flatness), so this is a three-level drill-down
    // rather than a single list+manage-children screen. The URL nests
    // region under its country so /{country}/{region} reads as a full path
    // and the breadcrumb can be built from route params alone; each
    // component's mount() still verifies the parent/child pair is
    // consistent (see Admin\Geography\Cities\Index::mount()).
    Route::prefix('geography')->name('geography.')->group(function () {
        Route::get('/', AdminGeographyCountriesIndex::class)->name('countries.index');
        Route::get('/{country}', AdminGeographyRegionsIndex::class)->name('regions.index');
        Route::get('/{country}/{region}', AdminGeographyCitiesIndex::class)->name('cities.index');
    });

    Route::prefix('sellers')->name('sellers.')->group(function () {
        Route::get('/', AdminSellersIndex::class)->name('index');
        Route::get('/{seller}', AdminSellersShow::class)->name('show');
    });

    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', AdminProductsIndex::class)->name('index');
        Route::get('/{product}', AdminProductsShow::class)->name('show');
    });

    // Buyers' raw contact details (phone/company/email) live here — Super
    // Admin only, not any admin role, even though today Super Admin is the
    // only admin role that exists. See EnsureUserIsSuperAdmin.
    Route::prefix('offers')->name('offers.')->middleware('super_admin')->group(function () {
        Route::get('/', AdminOffersIndex::class)->name('index');
        Route::get('/{offerRequest}', AdminOffersShow::class)->name('show');
    });

    Route::prefix('banners')->name('banners.')->group(function () {
        Route::get('/', AdminBannersIndex::class)->name('index');
        // /create must be registered before /{banner}/edit so "create"
        // isn't swallowed by the {banner} route-model-binding parameter.
        Route::get('/create', AdminBannersForm::class)->name('create');
        Route::get('/{banner}/edit', AdminBannersForm::class)->name('edit');
    });

    Route::prefix('articles')->name('articles.')->group(function () {
        Route::get('/', AdminArticlesIndex::class)->name('index');
        // /create must be registered before /{article}/edit, same reasoning
        // as admin/banners above.
        Route::get('/create', AdminArticlesForm::class)->name('create');
        Route::get('/{article}/edit', AdminArticlesForm::class)->name('edit');
    });

    // Sitewide config (analytics IDs, verification tokens, contact info,
    // default SEO tags) — Super Admin only, same reasoning as admin/offers.
    Route::get('/settings', AdminSettingsShow::class)->name('settings.show')->middleware('super_admin');
});
