<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use App\Models\Seller;
use App\Models\Setting;
use App\Services\SitemapGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SitemapGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Feature tests write a real public/sitemap.xml (see
        // SitemapGeneratorService::generateAndStore()'s own docblock on why
        // it can't use the fakeable `public` Storage disk) — clean up after
        // every test so runs don't leak a stale file into the repo.
        File::delete(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function makeCategory(): Category
    {
        $locales = config('ribbon.locales');

        return Category::create([
            'name' => ['uz' => 'Lentalar', 'ru' => 'Ленты', 'en' => 'Ribbons'],
            'slug' => ['uz' => 'lentalar', 'ru' => 'lenty', 'en' => 'ribbons'],
            'is_active' => true,
        ]);
    }

    private function makeSeller(): Seller
    {
        $country = Country::create(['name' => array_fill_keys(config('ribbon.locales'), 'Uzbekistan')]);
        $region = Region::create(['country_id' => $country->id, 'name' => array_fill_keys(config('ribbon.locales'), 'Tashkent')]);
        $city = City::create(['region_id' => $region->id, 'name' => array_fill_keys(config('ribbon.locales'), 'Tashkent')]);

        return Seller::register(
            [
                'name' => 'Test Seller LLC',
                'address' => 'Main warehouse',
                'country_id' => $country->id,
                'region_id' => $region->id,
                'city_id' => $city->id,
                'vat_number' => '123456789',
                'phone' => '+998901234567',
            ],
            [
                'name' => 'Test Owner',
                'email' => 'owner@example.test',
                'password' => 'password',
                'locale' => 'uz',
            ],
        );
    }

    private function makeProduct(Category $category, Seller $seller, string $status = 'approved'): Product
    {
        // products.brand_id defaults to 1 ("No Brand", see BrandSeeder) but
        // that row isn't seeded outside the real seeders — create it here
        // so the FK constraint on brand_id is satisfied.
        $brand = Brand::first() ?? Brand::create(['name' => 'No Brand']);

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Test Product',
            'slug' => ['uz' => 'test-mahsulot', 'ru' => 'test-produkt', 'en' => 'test-product'],
            'status' => $status,
        ]);
    }

    private function makeArticle(): Article
    {
        $locales = config('ribbon.locales');

        return Article::create([
            'type' => 'article',
            'title' => array_fill_keys($locales, 'How ribbons work'),
            'slug' => ['uz' => 'lentalar-haqida', 'ru' => 'o-lentah', 'en' => 'how-ribbons-work'],
            'excerpt' => array_fill_keys($locales, 'Excerpt'),
            'body' => array_fill_keys($locales, '<p>Body</p>'),
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_generate_returns_valid_xml_with_expected_urls(): void
    {
        $category = $this->makeCategory();
        $seller = $this->makeSeller();
        $product = $this->makeProduct($category, $seller);
        $this->makeArticle();

        $xml = app(SitemapGeneratorService::class)->generate();

        // Well-formed XML with the expected root element/namespaces.
        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', $xml);
        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);

        // Home + articles index (no slug, `?lang=` variants).
        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $xml);
        $this->assertStringContainsString(url('/').'?lang=ru', $xml);
        $this->assertStringContainsString('<loc>'.url('/articles').'</loc>', $xml);

        // Category, all 3 locale slugs.
        $this->assertStringContainsString(route('storefront.catalog.show', ['categorySlug' => 'ribbons']), $xml);
        $this->assertStringContainsString(route('storefront.catalog.show', ['categorySlug' => 'lenty']).'?lang=ru', $xml);

        // Product, all 3 locale slugs.
        $this->assertStringContainsString(route('storefront.products.show', ['productSlug' => 'test-product']), $xml);
        $this->assertStringContainsString(route('storefront.products.show', ['productSlug' => 'test-mahsulot']), $xml);

        // Article.
        $this->assertStringContainsString(route('storefront.articles.show', ['articleSlug' => 'how-ribbons-work']), $xml);

        // hreflang alternates, including x-default.
        $this->assertStringContainsString('hreflang="x-default"', $xml);
        $this->assertStringContainsString('hreflang="ru"', $xml);
        $this->assertStringContainsString('hreflang="en"', $xml);

        // Excluded pages never appear.
        $this->assertStringNotContainsString(url('/search'), $xml);
        $this->assertStringNotContainsString(url('/request'), $xml);
        $this->assertStringNotContainsString(url('/login'), $xml);
        $this->assertStringNotContainsString(url('/sellers/register'), $xml);

        // A non-approved product must never leak into the sitemap.
        $this->makeProduct($category, $seller, 'pending');
        $xmlAfterPending = app(SitemapGeneratorService::class)->generate();
        $this->assertSame(
            substr_count($xml, '<url>'),
            substr_count($xmlAfterPending, '<url>'),
        );
    }

    public function test_artisan_command_generates_and_stores_the_sitemap(): void
    {
        $category = $this->makeCategory();
        $seller = $this->makeSeller();
        $this->makeProduct($category, $seller);

        $this->assertFalse(File::exists(public_path('sitemap.xml')));

        $this->artisan('sitemap:generate')
            ->expectsOutputToContain('Sitemap generated')
            ->assertSuccessful();

        $this->assertTrue(File::exists(public_path('sitemap.xml')));
        $this->assertNotNull(Setting::current()->sitemap_generated_at);
    }

    public function test_public_route_404s_before_generation_and_200s_with_xml_after(): void
    {
        $this->get(route('sitemap.xml'))->assertNotFound();

        app(SitemapGeneratorService::class)->generateAndStore();

        $response = $this->get(route('sitemap.xml'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<urlset', false);
    }
}
