<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\CategoryParameterOption;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use App\Models\Seller;
use App\Services\OfferSelectionService;
use App\Livewire\Storefront\Catalog\Show as CatalogShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Smoke + contract checks for the redesigned storefront shell (spec 18):
 * every public page renders, keeps its single H1 and head SEO block, and the
 * one-bottom-bar rule holds.
 */
class StorefrontPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => 'Seller One', 'address' => 'Addr', 'country_id' => $country->id, 'region_id' => $region->id, 'city_id' => $city->id, 'vat_number' => '1001', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => 'approved'])->save();

        $category = Category::create([
            'name' => ['uz' => 'Lentalar', 'ru' => 'Ленты', 'en' => 'Ribbons'],
            'slug' => ['uz' => 'lentalar', 'ru' => 'lenty', 'en' => 'ribbons'],
            'is_active' => true,
            'requires_subscription' => false,
        ]);

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => (Brand::first() ?? Brand::create(['name' => 'No Brand']))->id,
            'name' => 'Ribbon p1',
            'slug' => ['uz' => 'p1-uz', 'ru' => 'p1-ru', 'en' => 'p1-en'],
            'status' => 'approved',
        ]);
    }

    public function test_public_pages_render_with_one_h1_and_head_seo(): void
    {
        $this->makeProduct();

        foreach (['/', '/catalog/lentalar', '/products/p1-uz', '/search?q=Ribbon', '/search?q=zzzzzz', '/search', '/request', '/articles'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "$url must have exactly one H1");
            $this->assertStringContainsString('viewport-fit=cover', $html);
            $this->assertStringContainsString('<a href="#main"', $html);
            $this->assertStringNotContainsString('+998901234567', $html, "$url must not leak the seller phone");
        }

        $this->get('/products/p1-uz')->assertSee('rel="canonical"', false)->assertSee('hreflang="ru"', false)->assertSee('application/ld+json', false);
    }

    public function test_product_page_has_the_product_bar_and_no_selection_bar(): void
    {
        $product = $this->makeProduct();
        OfferSelectionService::ensure($product->id);

        $html = $this->get('/products/p1-uz')->assertOk()->getContent();

        // One fixed bottom bar: the product bar (one yellow CTA at lg-). Selection bar component absent.
        $this->assertSame(1, substr_count($html, 'fixed inset-x-0 bottom-0'));
        $this->assertStringNotContainsString('Send request', $html);
    }

    public function test_catalog_shows_selection_bar_and_request_page_has_none(): void
    {
        $product = $this->makeProduct();
        OfferSelectionService::ensure($product->id);

        $this->get('/catalog/lentalar')->assertOk()
            ->assertSee('fixed inset-x-0 bottom-0', false);

        $this->get('/request')->assertOk()
            ->assertDontSee('fixed inset-x-0 bottom-0', false);
    }

    public function test_catalog_with_filters_and_specs_renders_both_filter_variants(): void
    {
        $product = $this->makeProduct();
        $names = fn (string $v) => ['uz' => $v, 'ru' => $v, 'en' => $v];

        $select = CategoryParameter::create(['category_id' => $product->category_id, 'name' => $names('Width'), 'type' => 'select_single', 'is_filterable' => true, 'sort_order' => 1]);
        for ($i = 1; $i <= 9; $i++) {
            CategoryParameterOption::create(['category_parameter_id' => $select->id, 'value' => $names("W$i"), 'sort_order' => $i]);
        }
        CategoryParameter::create(['category_id' => $product->category_id, 'name' => $names('Length'), 'type' => 'number', 'unit' => 'm', 'is_filterable' => true, 'sort_order' => 2]);

        $html = $this->get('/catalog/lentalar')->assertOk()->getContent();

        $this->assertStringContainsString('id="fg-'.$select->id.'"', $html);
        $this->assertStringContainsString('id="fs-'.$select->id.'"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('rel=', $html);
    }

    /** QA N-01: ticking the first option of a filter crashed with a 500. */
    public function test_option_filter_keys_are_seeded_and_non_list_values_are_coerced(): void
    {
        $product = $this->makeProduct();
        $names = fn (string $v) => ['uz' => $v, 'ru' => $v, 'en' => $v];
        $select = CategoryParameter::create(['category_id' => $product->category_id, 'name' => $names('Width'), 'type' => 'select_single', 'is_filterable' => true, 'sort_order' => 1]);
        $option = CategoryParameterOption::create(['category_parameter_id' => $select->id, 'value' => $names('W1'), 'sort_order' => 1]);

        $component = Livewire::test(CatalogShow::class, ['categorySlug' => 'lentalar'])
            ->assertSet('selected.'.$select->id, []);

        // What the browser sent before the fix: a bare boolean.
        $component->set('selected.'.$select->id, true)
            ->assertOk()
            ->assertSet('selected.'.$select->id, []);

        $component->set('selected.'.$select->id, [(string) $option->id])
            ->assertOk()
            ->assertSet('selected.'.$select->id, [(string) $option->id]);

        $component->call('removeOptionFilter', $select->id, (string) $option->id)
            ->assertSet('selected.'.$select->id, []);
    }
}
