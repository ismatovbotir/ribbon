<?php

namespace Tests\Feature;

use App\Livewire\Sellers\Products\Edit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use App\Models\Seller;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RibbonCoreWidthTest extends TestCase
{
    use RefreshDatabase;

    private CategoryParameter $width;

    private CategoryParameter $coreWidth;

    private function makeParameters(): Category
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $category = Category::create([
            'name' => $names,
            'slug' => array_fill_keys(config('ribbon.locales'), 'ribbons'),
            'is_active' => true,
            'requires_subscription' => false,
        ]);

        $this->width = CategoryParameter::create([
            'category_id' => $category->id, 'code' => CategoryParameter::CODE_RIBBON_WIDTH, 'name' => $names,
            'type' => 'number', 'unit' => 'mm', 'is_required' => true, 'sort_order' => 1,
        ]);
        $this->coreWidth = CategoryParameter::create([
            'category_id' => $category->id, 'code' => CategoryParameter::CODE_RIBBON_CORE_WIDTH, 'name' => $names,
            'type' => 'number', 'unit' => 'mm', 'is_required' => false, 'is_filterable' => false, 'sort_order' => 2,
        ]);

        return $category;
    }

    private function label(mixed $width, mixed $coreWidth, string $locale = 'en'): string
    {
        return Product::composeLabel(null, [$this->width, $this->coreWidth], [
            $this->width->id => $width,
            $this->coreWidth->id => $coreWidth,
        ], null, $locale);
    }

    public function test_title_mentions_core_width_only_when_it_differs(): void
    {
        $this->makeParameters();

        $this->assertSame('60mm', $this->label(60, null));
        $this->assertSame('60mm', $this->label(60, '60.0'));
        $this->assertSame('60mm core 110mm', $this->label(60, 110));
        $this->assertSame('60mm втулка 110mm', $this->label(60, 110, 'ru'));
        $this->assertSame('60mm vtulka 110mm', $this->label(60, 110, 'uz'));
    }

    public function test_storefront_name_follows_the_same_rule(): void
    {
        $category = $this->makeParameters();
        $product = $this->product($category);
        $product->parameterValues()->create(['category_parameter_id' => $this->width->id, 'value_number' => 60]);
        $core = $product->parameterValues()->create(['category_parameter_id' => $this->coreWidth->id, 'value_number' => 110]);

        $fresh = fn () => Product::with('parameterValues.categoryParameter', 'parameterValues.options.categoryParameterOption', 'brand')->find($product->id);

        $this->assertSame('60mm core 110mm', $fresh()->localizedName('en'));

        $core->update(['value_number' => 60]);

        $this->assertSame('60mm', $fresh()->localizedName('en'));
    }

    private function product(Category $category): Product
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => 'Seller', 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id,
                'city_id' => $city->id, 'vat_number' => '2001', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => 'approved'])->save();

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => (Brand::first() ?? Brand::create(['name' => 'No Brand']))->id,
            'name' => 'Ribbon',
            'slug' => ['uz' => 'p-uz', 'ru' => 'p-ru', 'en' => 'p-en'],
            'status' => 'pending',
        ]);
    }

    public function test_seller_form_rejects_a_core_narrower_than_the_ribbon(): void
    {
        $product = $this->product($this->makeParameters());
        $owner = $product->seller->users()->firstOrFail();
        $key = "parameterValues.{$this->coreWidth->id}";

        $component = Livewire::actingAs($owner)->test(Edit::class, ['product' => $product])
            ->set("parameterValues.{$this->width->id}", 60);

        $component->set($key, 40)->call('saveSpecifications')->assertHasErrors([$key]);
        $component->set($key, 110)->call('saveSpecifications')->assertHasNoErrors();
        $component->set($key, null)->call('saveSpecifications')->assertHasNoErrors();
    }

    public function test_seeded_ribbon_category_has_optional_unfiltered_core_width(): void
    {
        $this->seed(CatalogSeeder::class);

        $coreWidth = CategoryParameter::where('code', CategoryParameter::CODE_RIBBON_CORE_WIDTH)->sole();

        $this->assertFalse($coreWidth->is_required);
        $this->assertFalse($coreWidth->is_filterable);
        $this->assertSame('mm', $coreWidth->unit);
        $this->assertSame(
            CategoryParameter::where('code', CategoryParameter::CODE_RIBBON_WIDTH)->sole()->category_id,
            $coreWidth->category_id,
        );
    }
}
