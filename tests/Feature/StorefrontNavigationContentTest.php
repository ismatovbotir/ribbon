<?php

namespace Tests\Feature;

use App\Jobs\NotifyTelegramOfNewContactMessage;
use App\Livewire\Admin\ContactMessages\Index as ContactMessagesIndex;
use App\Livewire\Admin\Pages\Form as PagesForm;
use App\Livewire\Admin\Pages\Index as PagesIndex;
use App\Livewire\Storefront\Catalog\Show as CatalogShow;
use App\Livewire\Storefront\Contact;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\CategoryParameterOption;
use App\Models\City;
use App\Models\ContactMessage;
use App\Models\Country;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductParameterValue;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\NavMenu;
use App\Services\SitemapGeneratorService;
use App\Support\CatalogFilterUrl;
use Database\Seeders\PageSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Spec 19: CMS pages, news split, contact form, nav menu data, catalog
 * filter URLs, permissions, sitemap.
 */
class StorefrontNavigationContentTest extends TestCase
{
    use RefreshDatabase;

    // ---------- helpers ----------

    private function seedPages(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PageSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    private function moderator(): User
    {
        $this->seed(PermissionSeeder::class);
        $role = Role::create(['type' => 'admin', 'name' => 'Moderator', 'slug' => 'moderator-'.uniqid(), 'is_super_admin' => false]);
        $role->permissions()->attach(Permission::where('slug', 'pages.manage')->firstOrFail());

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function makeSeller(string $status = 'approved'): Seller
    {
        static $n = 0;
        $n++;
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::first() ?? Country::create(['name' => $names]);
        $region = Region::first() ?? Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::first() ?? City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => "Seller $n", 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id,
                'city_id' => $city->id, 'vat_number' => "300$n", 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => "nav$n@example.test", 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => $status])->save();

        return $seller;
    }

    private function makeCategory(): Category
    {
        // "No Brand" must be brand id 1 (the products.brand_id default).
        Brand::firstOrCreate(['name' => 'No Brand']);

        return Category::create([
            'name' => ['uz' => 'Lentalar', 'ru' => 'Ленты', 'en' => 'Ribbons'],
            'slug' => ['uz' => 'lentalar', 'ru' => 'lenty', 'en' => 'ribbons'],
            'is_active' => true,
            'requires_subscription' => false,
        ]);
    }

    private function makeProduct(Category $category, Seller $seller, ?Brand $brand = null, string $status = 'approved'): Product
    {
        static $n = 0;
        $n++;
        $brand ??= Brand::first() ?? Brand::create(['name' => 'No Brand']);

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => "Product $n",
            'slug' => ['uz' => "p$n-uz", 'ru' => "p$n-ru", 'en' => "p$n-en"],
            'status' => $status,
        ]);
    }

    private function selectParameter(Category $category): array
    {
        $param = CategoryParameter::create([
            'category_id' => $category->id,
            'name' => ['uz' => 'Turi', 'ru' => 'Тип', 'en' => 'Type'],
            'type' => 'select_single',
            'is_required' => false,
            'is_filterable' => true,
            'sort_order' => 1,
        ]);

        $wax = CategoryParameterOption::create(['category_parameter_id' => $param->id, 'value' => ['uz' => 'Mum', 'ru' => 'Воск', 'en' => 'Wax'], 'sort_order' => 1]);
        $resin = CategoryParameterOption::create(['category_parameter_id' => $param->id, 'value' => ['uz' => 'Smola', 'ru' => 'Смола', 'en' => 'Resin'], 'sort_order' => 2]);

        return [$param, $wax, $resin];
    }

    private function assignOption(Product $product, CategoryParameter $param, CategoryParameterOption $option): void
    {
        $value = ProductParameterValue::create(['product_id' => $product->id, 'category_parameter_id' => $param->id]);
        $value->options()->create(['category_parameter_option_id' => $option->id]);
    }

    private function makeArticle(string $type, string $slugBase, ?string $publishedAt = 'now'): Article
    {
        $locales = config('ribbon.locales');

        return Article::create([
            'type' => $type,
            'title' => array_fill_keys($locales, "Title $slugBase"),
            'slug' => ['uz' => "$slugBase-uz", 'ru' => "$slugBase-ru", 'en' => "$slugBase-en"],
            'excerpt' => array_fill_keys($locales, 'x'),
            'body' => array_fill_keys($locales, '<p>Body</p>'),
            'published_at' => $publishedAt ? now()->subDay() : null,
        ]);
    }

    // ---------- pages ----------

    public function test_each_fixed_page_route_renders_with_its_content(): void
    {
        $this->seedPages();

        foreach (['/about', '/for-sellers', '/privacy', '/terms', '/contact'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/about')->assertSee('Ribbon');
        $this->get('/privacy')->assertSee('Maxfiylik siyosati');
        $this->get('/for-sellers?lang=en')->assertSee('Thermal transfer ribbons are free', false);
    }

    public function test_unpublished_or_missing_pages_are_404(): void
    {
        $this->seedPages();

        Page::where('key', 'privacy')->update(['is_published' => false]);
        Page::where('key', 'about')->update(['is_published' => false]);

        $this->get('/privacy')->assertNotFound();
        $this->get('/about')->assertNotFound();
        $this->get('/terms')->assertOk();
        $this->get('/p/does-not-exist')->assertNotFound();
        // System pages are not reachable through the custom route.
        $this->get('/p/about')->assertNotFound();
    }

    public function test_custom_page_resolves_per_locale_slug_and_hides_when_unpublished(): void
    {
        $page = Page::create([
            'slug' => ['uz' => 'yetkazish', 'ru' => 'dostavka', 'en' => 'delivery'],
            'title' => ['uz' => 'Yetkazish', 'ru' => 'Доставка', 'en' => 'Delivery'],
            'body' => ['uz' => '<p>uz body</p>', 'ru' => '<p>ru body</p>', 'en' => '<p>en body</p>'],
        ]);

        $this->get('/p/yetkazish')->assertOk()->assertSee('uz body', false);
        $this->get('/p/dostavka?lang=ru')->assertOk()->assertSee('ru body', false);
        $this->get('/p/delivery?lang=en')->assertOk()->assertSee('en body', false);
        // Other locale's slug redirects to the current locale's canonical slug.
        $this->get('/p/delivery?lang=ru')->assertRedirect('/p/dostavka');

        $page->update(['is_published' => false]);
        $this->get('/p/yetkazish')->assertNotFound();
    }

    public function test_page_body_is_sanitized_on_save_and_headings_limited_to_h2_h3(): void
    {
        $page = Page::create([
            'slug' => ['uz' => 'x', 'ru' => 'x', 'en' => 'x'],
            'title' => array_fill_keys(config('ribbon.locales'), 'T'),
            'body' => array_fill_keys(config('ribbon.locales'), '<h1>Big</h1><h4>Small</h4><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">l</a>'),
        ]);

        $body = $page->fresh()->body['uz'];

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('<h1', $body);
        $this->assertStringNotContainsString('<h4', $body);
        $this->assertStringContainsString('<h2>Big</h2>', $body);
        $this->assertStringContainsString('<h3>Small</h3>', $body);
    }

    public function test_page_key_is_immutable_and_seeder_is_idempotent(): void
    {
        $this->seedPages();
        $this->seed(PageSeeder::class);

        $this->assertSame(5, Page::count());

        $this->expectException(\LogicException::class);
        Page::where('key', 'about')->first()->update(['key' => 'other']);
    }

    // ---------- news / articles ----------

    public function test_news_lists_only_news_and_articles_lists_only_articles(): void
    {
        $this->makeArticle('news', 'newsy');
        $this->makeArticle('article', 'guide');

        // The mega menu's featured-article slot is on every page, so compare the <main> list region only.
        $main = fn (string $html) => substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));

        $news = $main($this->get('/news')->assertOk()->getContent());
        $this->assertStringContainsString('Title newsy', $news);
        $this->assertStringNotContainsString('Title guide', $news);

        $articles = $main($this->get('/articles')->assertOk()->getContent());
        $this->assertStringContainsString('Title guide', $articles);
        $this->assertStringNotContainsString('Title newsy', $articles);
    }

    public function test_old_type_query_and_wrong_type_slugs_301_to_correct_route(): void
    {
        $this->makeArticle('news', 'newsy');
        $this->makeArticle('article', 'guide');

        $this->get('/articles?type=news')->assertStatus(301)->assertRedirect('/news');
        $this->get('/articles?type=article')->assertStatus(301)->assertRedirect('/articles');
        $this->get('/articles/newsy-uz')->assertStatus(301)->assertRedirect('/news/newsy-uz');
        $this->get('/news/guide-uz')->assertStatus(301)->assertRedirect('/articles/guide-uz');

        $this->get('/news/newsy-uz')->assertOk()->assertSee('NewsArticle', false);
        $this->get('/articles/guide-uz')->assertOk();

        // Last: ?lang= sticks to the session for later requests.
        $this->get('/articles?type=news&lang=ru')->assertStatus(301)->assertRedirect('/news?lang=ru');
    }

    public function test_empty_news_is_noindex_and_unpublished_news_is_404(): void
    {
        $this->get('/news')->assertOk()->assertSee('noindex', false);

        $draft = $this->makeArticle('news', 'draftnews', null);
        $this->get('/news/'.$draft->slug['uz'])->assertNotFound();
    }

    // ---------- nav menu ----------

    public function test_mega_menu_counts_and_options_use_only_visible_products(): void
    {
        $category = $this->makeCategory();
        [$param, $wax, $resin] = $this->selectParameter($category);
        $zebra = Brand::create(['name' => 'Zebra']);
        $hidden = Brand::create(['name' => 'Hidden']);

        $approved = $this->makeSeller();
        $pending = $this->makeSeller('pending');

        $visible = $this->makeProduct($category, $approved, $zebra);
        $this->assignOption($visible, $param, $wax);

        // Hidden three ways: product pending, seller pending.
        $pendingProduct = $this->makeProduct($category, $approved, $hidden, 'pending');
        $this->assignOption($pendingProduct, $param, $resin);
        $sellerPending = $this->makeProduct($category, $pending, $hidden);
        $this->assignOption($sellerPending, $param, $resin);

        $mega = app(NavMenu::class)->megaMenu('en');

        $this->assertCount(1, $mega['categories']);
        $row = $mega['categories'][0];
        $this->assertSame(1, $row['count']);
        $this->assertSame('Ribbons', $row['name']);
        $this->assertCount(1, $row['groups']);
        $this->assertSame('Type', $row['groups'][0]['label']);
        $this->assertSame(['Wax'], array_column($row['groups'][0]['links'], 'label'));
        $this->assertSame(['Zebra'], array_column($row['brands'], 'label'));
        $this->assertNull($mega['article']);
    }

    public function test_mega_menu_excludes_no_brand_caps_and_orders_by_count(): void
    {
        $category = $this->makeCategory();
        [$param, $wax, $resin] = $this->selectParameter($category);
        $seller = $this->makeSeller();

        $noBrand = Brand::first() ?? Brand::create(['name' => 'No Brand']);
        $brands = collect(['A', 'B', 'C', 'D', 'E', 'F', 'G'])->map(fn ($n) => Brand::create(['name' => $n]));

        // resin x2, wax x1 -> resin first despite sort_order.
        foreach ([$resin, $resin, $wax] as $option) {
            $this->assignOption($this->makeProduct($category, $seller, $noBrand), $param, $option);
        }

        foreach ($brands as $brand) {
            $this->makeProduct($category, $seller, $brand);
        }

        $row = app(NavMenu::class)->megaMenu('en')['categories'][0];

        $this->assertSame(['Resin', 'Wax'], array_column($row['groups'][0]['links'], 'label'));
        $this->assertCount(6, $row['brands']);
        $this->assertNotContains('No Brand', array_column($row['brands'], 'label'));
    }

    public function test_mega_menu_two_pane_data_icon_sellers_guide_and_empty(): void
    {
        $category = $this->makeCategory();
        $category->update(['icon' => 'ribbon-roll']);

        $empty = Category::create([
            'name' => array_fill_keys(config('ribbon.locales'), 'Scanners'),
            'slug' => ['uz' => 'skaner', 'ru' => 'skaner', 'en' => 'scanners'],
            'is_active' => true, 'requires_subscription' => true, 'sort_order' => 2,
            'icon' => 'not-a-real-icon',
        ]);

        // Two approved sellers with visible products (one has two), one
        // pending seller and one hidden product: neither counts.
        [$a, $b] = [$this->makeSeller(), $this->makeSeller()];
        $this->makeProduct($category, $a);
        $this->makeProduct($category, $a);
        $this->makeProduct($category, $b);
        $this->makeProduct($category, $this->makeSeller('pending'));
        $this->makeProduct($category, $this->makeSeller(), null, 'pending');

        // Paid category, seller unpaid: hidden by the subscription scope.
        $this->makeProduct($empty, $a);

        $guide = $this->makeArticle('article', 'how-to-choose');
        $category->update(['guide_article_id' => $guide->id]);
        $draft = $this->makeArticle('article', 'draft', null);
        $empty->update(['guide_article_id' => $draft->id]);

        [$row, $emptyRow] = app(NavMenu::class)->megaMenu('en')['categories'];

        $this->assertSame('ribbon-roll', $row['icon']);
        $this->assertSame(2, $row['verified_sellers']);
        $this->assertSame('Title how-to-choose', $row['guide']['title']);
        $this->assertStringEndsWith('/articles/how-to-choose-en', $row['guide']['url']);

        $this->assertSame(NavMenu::DEFAULT_ICON, $emptyRow['icon']);
        $this->assertSame(0, $emptyRow['count']);
        $this->assertSame(0, $emptyRow['verified_sellers']);
        $this->assertNull($emptyRow['guide'], 'unpublished guides are never linked');

        // Deleting the guide article clears the link (app-level nullOnDelete).
        $guide->delete();
        $this->assertNull($category->fresh()->guide_article_id);
        $this->assertNull(app(NavMenu::class)->megaMenu('en')['categories'][0]['guide']);
    }

    public function test_mega_panel_renders_panes_and_seller_hook_for_empty_category(): void
    {
        $category = $this->makeCategory();
        $category->update(['icon' => 'ribbon-roll']);
        $this->makeProduct($category, $this->makeSeller());
        $empty = Category::create([
            'name' => array_fill_keys(config('ribbon.locales'), 'Scanners'),
            'slug' => ['uz' => 'skaner', 'ru' => 'skaner', 'en' => 'scanners'],
            'is_active' => true, 'requires_subscription' => true, 'sort_order' => 2,
        ]);

        $html = $this->get('/?lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('id="mega-pane-'.$category->id.'"', $html);
        $this->assertStringContainsString('id="mega-pane-'.$empty->id.'"', $html);
        $this->assertStringContainsString('1 verified seller', $html);
        $this->assertStringContainsString('Sell in this category on Ribbon', $html);
        $this->assertStringContainsString(route('sellers.register'), $html);
    }

    public function test_mega_menu_number_parameter_uses_most_frequent_values_with_unit(): void
    {
        $category = $this->makeCategory();
        $width = CategoryParameter::create([
            'category_id' => $category->id, 'name' => ['uz' => 'Eni', 'ru' => 'Ширина', 'en' => 'Width'],
            'type' => 'number', 'unit' => 'mm', 'is_filterable' => true, 'sort_order' => 1,
        ]);
        $seller = $this->makeSeller();

        foreach ([110, 110, 90] as $mm) {
            $p = $this->makeProduct($category, $seller);
            ProductParameterValue::create(['product_id' => $p->id, 'category_parameter_id' => $width->id, 'value_number' => $mm]);
        }

        $group = app(NavMenu::class)->megaMenu('en')['categories'][0]['groups'][0];

        $this->assertTrue($group['mono']);
        $this->assertSame(['110 mm', '90 mm'], array_column($group['links'], 'label'));
        $this->assertStringContainsString('min%5B'.$width->id.'%5D=110', $group['links'][0]['url']);
    }

    public function test_mega_menu_is_cached_per_locale_and_busts_on_save(): void
    {
        $category = $this->makeCategory();
        $seller = $this->makeSeller();
        $this->makeProduct($category, $seller);

        $nav = app(NavMenu::class);
        $this->assertSame(1, $nav->megaMenu('en')['categories'][0]['count']);
        $this->assertTrue(Cache::has(NavMenu::megaKey('en')));
        $this->assertFalse(Cache::has(NavMenu::megaKey('ru')));

        // Saving a Product busts the cache; new count is visible immediately.
        $this->makeProduct($category, $seller);
        $this->assertFalse(Cache::has(NavMenu::megaKey('en')));
        $this->assertSame(2, $nav->megaMenu('en')['categories'][0]['count']);

        // Each feeding model busts it.
        foreach ([
            fn () => $category->update(['sort_order' => 5]),
            fn () => Brand::create(['name' => 'Fresh']),
            fn () => $this->makeArticle('article', 'bust'),
            fn () => Page::create(['key' => null, 'slug' => ['uz' => 'q', 'ru' => 'q', 'en' => 'q'], 'title' => array_fill_keys(config('ribbon.locales'), 'Q'), 'body' => array_fill_keys(config('ribbon.locales'), '')]),
            fn () => $this->selectParameter($category),
        ] as $mutate) {
            $nav->megaMenu('ru');
            $this->assertTrue(Cache::has(NavMenu::megaKey('ru')));
            $mutate();
            $this->assertFalse(Cache::has(NavMenu::megaKey('ru')));
        }

        $this->assertNotNull($nav->megaMenu('en')['article']);
    }

    public function test_menu_pages_return_only_published_header_and_footer_pages(): void
    {
        $this->seedPages();
        $nav = app(NavMenu::class);

        $menu = $nav->menuPages('en');
        $this->assertSame(['about'], array_column($menu['header'], 'key'));
        $this->assertSame(['about', 'for-sellers'], array_column($menu['footer']['company'], 'key'));
        $this->assertSame(['privacy', 'terms'], array_column($menu['footer']['legal'], 'key'));
        $this->assertSame('/about', $menu['header'][0]['url']);

        Page::where('key', 'terms')->update(['is_published' => false]);
        Page::where('key', 'about')->first()->update(['show_in_header' => false]);

        $menu = $nav->menuPages('en');
        $this->assertSame([], $menu['header']);
        $this->assertSame(['privacy'], array_column($menu['footer']['legal'], 'key'));
    }

    public function test_layout_receives_mega_and_menu_pages_from_composer(): void
    {
        $this->seedPages();
        $category = $this->makeCategory();

        // Registered after the app's own composer, so it sees what it supplied.
        $captured = [];
        View::composer('layouts.storefront', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        $this->get('/catalog/lentalar')->assertOk();

        $this->assertTrue($captured['mega']['categories'][0]['active']);
        $this->assertSame($category->id, $captured['mega']['categories'][0]['id']);
        $this->assertSame('about', $captured['menuPages']['header'][0]['key']);
        $this->assertArrayHasKey('settings', $captured);
    }

    // ---------- CatalogFilterUrl ----------

    public function test_catalog_filter_url_round_trips_with_catalog_show(): void
    {
        $category = $this->makeCategory();
        [$param, $wax, $resin] = $this->selectParameter($category);
        $width = CategoryParameter::create([
            'category_id' => $category->id, 'name' => ['uz' => 'Eni', 'ru' => 'Ш', 'en' => 'Width'],
            'type' => 'number', 'unit' => 'mm', 'is_filterable' => true, 'sort_order' => 2,
        ]);
        $seller = $this->makeSeller();
        $zebra = Brand::create(['name' => 'Zebra']);

        $waxProduct = $this->makeProduct($category, $seller, $zebra);
        $this->assignOption($waxProduct, $param, $wax);
        ProductParameterValue::create(['product_id' => $waxProduct->id, 'category_parameter_id' => $width->id, 'value_number' => 110]);
        $resinProduct = $this->makeProduct($category, $seller);
        $this->assignOption($resinProduct, $param, $resin);

        $query = function (string $url): array {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

            return $q;
        };

        $this->assertSame('/catalog/lentalar', parse_url(CatalogFilterUrl::category($category, 'uz'), PHP_URL_PATH));
        $this->assertSame('/catalog/lenty', parse_url(CatalogFilterUrl::category($category, 'ru'), PHP_URL_PATH));

        // select option
        $url = CatalogFilterUrl::for($category, $param, $wax, 'uz');
        Livewire::withQueryParams($query($url))->test(CatalogShow::class, ['categorySlug' => 'lentalar'])
            ->assertSet('selected', [$param->id => [(string) $wax->id]])
            ->assertSee('/products/'.$waxProduct->slug['uz'])
            ->assertDontSee('/products/'.$resinProduct->slug['uz']);

        // number (exact value)
        $url = CatalogFilterUrl::for($category, $width, '110.000', 'uz');
        Livewire::withQueryParams($query($url))->test(CatalogShow::class, ['categorySlug' => 'lentalar'])
            ->assertSet('numMin', [$width->id => '110'])
            ->assertSet('numMax', [$width->id => '110'])
            ->assertSee('/products/'.$waxProduct->slug['uz'])
            ->assertDontSee('/products/'.$resinProduct->slug['uz']);

        // brand
        $url = CatalogFilterUrl::brand($category, $zebra, 'uz');
        Livewire::withQueryParams($query($url))->test(CatalogShow::class, ['categorySlug' => 'lentalar'])
            ->assertSet('brands', [(string) $zebra->id])
            ->assertSee('/products/'.$waxProduct->slug['uz'])
            ->assertDontSee('/products/'.$resinProduct->slug['uz']);

        // The page marks filtered shortcut URLs noindex.
        $this->get($url)->assertOk()->assertSee('noindex,follow', false);
    }

    // ---------- contact ----------

    private function contactComponent()
    {
        return Livewire::test(Contact::class);
    }

    private function submitContact($component, string $phone = '+998 90 123 45 67', string $message = 'hello')
    {
        // Pass the time trap.
        $this->travel(5)->seconds();

        return $component->set('phone', $phone)->set('message', $message)->call('submit');
    }

    public function test_contact_page_form_creates_row_and_notifies_without_failing(): void
    {
        Bus::fake([NotifyTelegramOfNewContactMessage::class]);

        $component = Livewire::withQueryParams(['utm_source' => 'tg', 'utm_medium' => 'post'])
            ->withHeaders(['referer' => 'https://google.com/search?q=secret'])
            ->test(Contact::class);

        $this->submitContact($component)->assertSet('submitted', true)->assertHasNoErrors();

        $row = ContactMessage::sole();
        $this->assertSame('contact', $row->source);
        $this->assertSame('+998 90 123 45 67', $row->phone);
        $this->assertSame('hello', $row->message);
        $this->assertSame('uz', $row->locale);
        $this->assertSame(['utm_source' => 'tg', 'utm_medium' => 'post'], $row->utm);
        $this->assertSame('https://google.com/search', $row->referrer);
        $this->assertSame('new', $row->status);
        Bus::assertDispatched(NotifyTelegramOfNewContactMessage::class);
    }

    public function test_contact_requires_phone_and_message_is_optional(): void
    {
        $component = $this->contactComponent();
        $this->travel(5)->seconds();
        $component->set('phone', '')->call('submit')->assertHasErrors(['phone' => 'required']);
        $this->assertSame(0, ContactMessage::count());

        $this->submitContact($this->contactComponent(), '+998901112233', '');
        $this->assertNull(ContactMessage::sole()->message);
    }

    public function test_contact_honeypot_and_time_trap_fake_success_without_row(): void
    {
        $component = $this->contactComponent();
        $this->travel(5)->seconds();
        $component->set('phone', '+998901112233')->set('website', 'http://spam')->call('submit')->assertSet('submitted', true);

        // Too fast: no travel after mount.
        $fast = $this->contactComponent();
        $fast->set('phone', '+998901112233')->call('submit')->assertSet('submitted', true);

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_contact_rendered_at_is_locked(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->contactComponent()->set('renderedAt', 1);
    }

    public function test_contact_rate_limits_per_ip_and_per_phone(): void
    {
        RateLimiter::clear('contact:ip:127.0.0.1');

        // 3 per hour per phone.
        for ($i = 0; $i < 3; $i++) {
            $this->submitContact($this->contactComponent(), '+998 90 000 00 01')->assertHasNoErrors();
        }

        $this->submitContact($this->contactComponent(), '99890-000-00-01')->assertHasErrors(['phone']);
        $this->assertSame(3, ContactMessage::count());

        // 5 per 10 min per IP across different phones (3 already used).
        RateLimiter::clear('contact:phone:998900000001');
        $this->submitContact($this->contactComponent(), '+998 91 000 00 02')->assertHasNoErrors();
        $this->submitContact($this->contactComponent(), '+998 91 000 00 03')->assertHasNoErrors();
        $this->submitContact($this->contactComponent(), '+998 91 000 00 04')->assertHasErrors(['phone']);
        $this->assertSame(5, ContactMessage::count());
    }

    public function test_contact_submission_never_logs_phone_or_message_even_if_telegram_fails(): void
    {
        config(['services.telegram.super_admin_chat_id' => '123', 'services.telegram.bot_token' => 'tok']);
        Http::fake(fn () => throw new \RuntimeException('network down'));

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event->message.' '.json_encode($event->context);
        });

        $this->submitContact($this->contactComponent(), '+998901112233', 'secret-note')
            ->assertSet('submitted', true);

        $this->assertSame(1, ContactMessage::count());

        foreach ($logged as $line) {
            $this->assertStringNotContainsString('998901112233', $line);
            $this->assertStringNotContainsString('secret-note', $line);
        }
    }

    // ---------- permissions ----------

    public function test_moderator_can_edit_about_but_not_legal_pages(): void
    {
        $this->seedPages();
        $moderator = $this->moderator();
        $about = Page::where('key', 'about')->first();
        $privacy = Page::where('key', 'privacy')->first();

        $this->assertTrue($about->isEditableBy($moderator));
        $this->assertFalse($privacy->isEditableBy($moderator));
        $this->assertTrue($privacy->isEditableBy($this->superAdmin()));

        $this->actingAs($moderator);

        Livewire::test(PagesForm::class, ['page' => $about])->assertOk();
        Livewire::test(PagesForm::class, ['page' => $privacy])->assertForbidden();
        Livewire::test(PagesIndex::class)->assertOk();
    }

    public function test_moderator_save_re_authorizes_and_cannot_move_page_to_legal_column(): void
    {
        $this->seedPages();
        $this->actingAs($this->moderator());
        $about = Page::where('key', 'about')->first();

        $component = Livewire::test(PagesForm::class, ['page' => $about]);

        $component->set('title.uz', 'Yangi sarlavha')->call('save')->assertHasNoErrors();
        $this->assertSame('Yangi sarlavha', $about->fresh()->title['uz']);

        Livewire::test(PagesForm::class, ['page' => $about->fresh()])
            ->set('footerColumn', 'legal')
            ->call('save')
            ->assertForbidden();
        $this->assertSame('company', $about->fresh()->footer_column);
    }

    public function test_moderator_cannot_delete_system_or_legal_pages_and_super_admin_can_delete_custom(): void
    {
        $this->seedPages();
        $custom = Page::create([
            'slug' => ['uz' => 'c', 'ru' => 'c', 'en' => 'c'],
            'title' => array_fill_keys(config('ribbon.locales'), 'C'),
            'body' => array_fill_keys(config('ribbon.locales'), '<p>c</p>'),
        ]);

        $this->actingAs($this->moderator());
        Livewire::test(PagesIndex::class)->call('delete', Page::where('key', 'about')->value('id'))->assertForbidden();
        Livewire::test(PagesIndex::class)->call('delete', Page::where('key', 'privacy')->value('id'))->assertForbidden();
        Livewire::test(PagesIndex::class)->call('delete', $custom->id)->assertHasNoErrors();
        $this->assertNull(Page::find($custom->id));
    }

    public function test_user_without_pages_manage_cannot_open_pages_admin(): void
    {
        $role = Role::create(['type' => 'admin', 'name' => 'Limited', 'slug' => 'limited', 'is_super_admin' => false]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user);
        Livewire::test(PagesIndex::class)->assertForbidden();
    }

    public function test_moderator_cannot_see_or_handle_contact_messages(): void
    {
        $message = ContactMessage::create(['source' => 'contact', 'phone' => '+998901112233', 'locale' => 'uz']);

        $this->actingAs($this->moderator());
        $this->get(route('admin.contact-messages.index'))->assertForbidden();
        Livewire::test(ContactMessagesIndex::class)->assertForbidden();
        $this->assertFalse(Gate::allows('super-admin'));

        $this->actingAs($this->superAdmin());
        $this->get(route('admin.contact-messages.index'))->assertOk()->assertSee('+998901112233');
        Livewire::test(ContactMessagesIndex::class)->call('markHandled', $message->id);
        $this->assertSame('handled', $message->fresh()->status);
    }

    public function test_pages_manage_permission_is_seeded(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->assertTrue(Permission::where('slug', 'pages.manage')->exists());
    }

    // ---------- sitemap ----------

    public function test_sitemap_includes_new_pages_news_and_custom_pages(): void
    {
        $this->seedPages();
        $this->makeArticle('news', 'newsy');
        $this->makeArticle('article', 'guide');
        Page::create([
            'slug' => ['uz' => 'yetkazish', 'ru' => 'dostavka', 'en' => 'delivery'],
            'title' => array_fill_keys(config('ribbon.locales'), 'D'),
            'body' => array_fill_keys(config('ribbon.locales'), '<p>d</p>'),
        ]);
        Page::where('key', 'terms')->update(['is_published' => false]);

        $xml = app(SitemapGeneratorService::class)->generate();

        foreach (['/about', '/for-sellers', '/privacy', '/contact', '/news', '/news/newsy-uz', '/articles/guide-uz', '/p/yetkazish'] as $path) {
            $this->assertStringContainsString('<loc>'.url($path).'</loc>', $xml, $path);
        }

        $this->assertStringContainsString('<loc>'.url('/about').'?lang=ru</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/p/dostavka').'?lang=ru</loc>', $xml);
        $this->assertStringNotContainsString(url('/terms'), $xml);
        $this->assertStringNotContainsString('/articles/newsy-uz', $xml);
        $this->assertStringNotContainsString('/news/guide-uz', $xml);
    }

    public function test_sitemap_omits_news_index_until_it_has_an_item(): void
    {
        $xml = app(SitemapGeneratorService::class)->generate();
        $this->assertStringNotContainsString('<loc>'.url('/news').'</loc>', $xml);
    }
}
