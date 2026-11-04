<?php

namespace Tests\Feature;

use App\Livewire\Storefront\Catalog\Show as CatalogShow;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use App\Models\Seller;
use App\Models\Setting;
use Database\Seeders\PageSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Spec 19 + 20 markup contracts: trust strip, footer legal bar, one yellow
 * CTA per view, no ARIA menu, round icon caps, catalog brand filter.
 */
class StorefrontEnterpriseUiTest extends TestCase
{
    use RefreshDatabase;

    private function seedPages(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PageSeeder::class);
    }

    private function category(): Category
    {
        Brand::firstOrCreate(['name' => 'No Brand']);

        return Category::create([
            'name' => ['uz' => 'Lentalar', 'ru' => 'Ленты', 'en' => 'Ribbons'],
            'slug' => ['uz' => 'lentalar', 'ru' => 'lenty', 'en' => 'ribbons'],
            'is_active' => true,
            'requires_subscription' => false,
        ]);
    }

    private function seller(): Seller
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => 'Seller One', 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id,
                'city_id' => $city->id, 'vat_number' => '3001', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => 'ui@example.test', 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => 'approved'])->save();

        return $seller;
    }

    private function main(string $html): string
    {
        $start = strpos($html, '<main');

        return substr($html, $start, strpos($html, '</main>') - $start);
    }

    public function test_home_trust_strip_is_a_list_of_four_without_headings(): void
    {
        $this->seedPages();

        $html = $this->get('/?lang=en')->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<section aria-label="Why buy on Ribbon"[^>]*>(.*?)<\/section>/s', $html, $m));
        $this->assertSame(4, substr_count($m[1], '<li'));
        $this->assertDoesNotMatchRegularExpression('/<h[1-6]/', $m[1]);
        $this->assertStringContainsString('Verified Uzbek companies (INN checked)', $m[1]);
        $this->assertStringContainsString('Bank transfer, cash or card', $m[1]);
        $this->assertStringContainsString('No checkout', $m[1]);
    }

    public function test_footer_always_shows_disclaimer_and_legal_identity_only_when_set(): void
    {
        $this->seedPages();

        $bare = $this->get('/?lang=en')->assertOk()->getContent();
        $this->assertStringContainsString('Ribbon is not a party to the sale.', $bare);
        $this->assertStringNotContainsString('Tax ID (INN)', $bare);

        Setting::current()->update(['legal_name' => 'LLC Test Co', 'legal_inn' => '123456789']);

        $full = $this->get('/?lang=en')->assertOk()->getContent();
        $this->assertStringContainsString('LLC Test Co', $full);
        $this->assertStringContainsString('Tax ID (INN)', $full);
        $this->assertStringContainsString('123456789', $full);
        $this->assertStringContainsString('Ribbon is not a party to the sale.', $full);
    }

    public function test_content_pages_have_at_most_the_selection_bar_cta_no_aria_menu_and_no_square_caps(): void
    {
        $this->seedPages();

        foreach (['/', '/contact', '/about', '/for-sellers', '/privacy', '/news', '/articles'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            // The only yellow control allowed is the selection bar's (always in the DOM, hidden until items exist).
            $this->assertLessThanOrEqual(1, substr_count($html, 'text-cta-text'), "$path renders more than one yellow CTA");
            $this->assertSame(0, substr_count($this->main($html), 'text-cta-text'), "$path has a yellow CTA in its content");
            $this->assertStringNotContainsString('role="menu"', $html, $path);
            // Chrome (header, drawer, footer) uses the round-cap icon set; shared glyphs inside <main> (empty states) are exempt.
            $this->assertStringNotContainsString('stroke-linecap="square"', str_replace($this->main($html), '', $html), $path);
            $this->assertSame(1, substr_count($html, '<h1'), "$path must have exactly one h1");
        }
    }

    public function test_header_has_catalog_trigger_content_strip_and_footer_legal_links(): void
    {
        $this->seedPages();
        $this->category();

        $html = $this->get('/?lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('aria-controls="mega-catalog"', $html);
        $this->assertStringContainsString('id="mega-catalog"', $html);
        $this->assertStringContainsString(url('/news').'"', $html);
        $this->assertStringContainsString(url('/contact').'"', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*/privacy"#', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*/terms"#', $html);
        // Category links exist in the mega panel, the chip row and the footer.
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'href="'.url('/catalog/ribbons').'"'));
    }

    public function test_request_page_hides_the_content_strip(): void
    {
        $this->seedPages();

        $request = $this->get('/request')->assertOk()->getContent();
        $home = $this->get('/')->assertOk()->getContent();

        $label = __('storefront.nav.main_label');

        $this->assertStringNotContainsString('aria-label="'.$label.'"', $request);
        $this->assertStringContainsString('aria-label="'.$label.'"', $home);
    }

    public function test_catalog_brand_filter_lists_brands_and_filters_products(): void
    {
        $category = $this->category();
        $seller = $this->seller();
        $zebra = Brand::create(['name' => 'Zebra']);
        $honeywell = Brand::create(['name' => 'Honeywell']);

        foreach ([['Zebra ZT-1', $zebra], ['Honeywell HW-2', $honeywell]] as $i => [$name, $brand]) {
            Product::create([
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => $name,
                'slug' => ['uz' => "b$i-uz", 'ru' => "b$i-ru", 'en' => "b$i-en"],
                'status' => 'approved',
            ]);
        }

        // Rows are identified by their product URL (the visible title is built from brand/specs).
        $component = Livewire::test(CatalogShow::class, ['categorySlug' => 'lentalar'])
            ->assertSee('/products/b0-uz', false)
            ->assertSee('/products/b1-uz', false)
            ->assertSee('Brend');

        $component->set('brands', [(string) $zebra->id])
            ->assertSee('/products/b0-uz', false)
            ->assertDontSee('/products/b1-uz', false);

        $component->call('removeBrandFilter', $zebra->id)->assertSee('/products/b1-uz', false);
    }
}
