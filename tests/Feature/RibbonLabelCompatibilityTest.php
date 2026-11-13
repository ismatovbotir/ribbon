<?php

namespace Tests\Feature;

use App\Livewire\Admin\Compatibility\Show as CompatibilityShow;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\CategoryParameterOption;
use App\Models\City;
use App\Models\Country;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductParameterValue;
use App\Models\ProductParameterValueOption;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\RibbonLabelCompatibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RibbonLabelCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private Category $ribbons;

    private Category $labels;

    private CategoryParameter $type;

    private CategoryParameter $ribbonWidth;

    private CategoryParameter $material;

    private CategoryParameter $labelWidth;

    /** @var array<string, CategoryParameterOption> */
    private array $opt = [];

    private Seller $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ribbons = $this->category('ribbons', false);
        $this->labels = $this->category('labels', true);

        $this->type = $this->parameter($this->ribbons, CategoryParameter::CODE_RIBBON_TYPE, 'select_single', ['Wax', 'Resin']);
        $this->ribbonWidth = $this->parameter($this->ribbons, CategoryParameter::CODE_RIBBON_WIDTH, 'number');
        $this->material = $this->parameter($this->labels, CategoryParameter::CODE_LABEL_MATERIAL, 'select_single', ['Vellum', 'PET']);
        $this->labelWidth = $this->parameter($this->labels, CategoryParameter::CODE_LABEL_WIDTH, 'number');

        $this->seller = $this->makeSeller();
    }

    private function category(string $key, bool $paid): Category
    {
        return Category::create([
            'name' => array_fill_keys(config('ribbon.locales'), ucfirst($key)),
            'slug' => array_fill_keys(config('ribbon.locales'), $key),
            'is_active' => true,
            'requires_subscription' => $paid,
        ]);
    }

    private function parameter(Category $category, string $code, string $type, array $options = []): CategoryParameter
    {
        $parameter = CategoryParameter::create([
            'category_id' => $category->id,
            'code' => $code,
            'name' => array_fill_keys(config('ribbon.locales'), $code),
            'type' => $type,
            'unit' => $type === 'number' ? 'mm' : null,
            'is_required' => true,
            'is_filterable' => true,
            'sort_order' => 1,
        ]);

        foreach ($options as $i => $value) {
            $this->opt[$value] = CategoryParameterOption::create([
                'category_parameter_id' => $parameter->id,
                'value' => array_fill_keys(config('ribbon.locales'), $value),
                'sort_order' => $i,
            ]);
        }

        return $parameter;
    }

    private function makeSeller(): Seller
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => 'Fit Seller', 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id,
                'city_id' => $city->id, 'vat_number' => '300100', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => 'fit-owner@example.test', 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => 'approved', 'subscription_paid_until' => now()->addMonth()])->save();

        return $seller;
    }

    private function product(Category $category, string $key, CategoryParameter $select, string $option, CategoryParameter $width, int $mm, string $status = 'approved'): Product
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'brand_id' => (Brand::first() ?? Brand::create(['name' => 'No Brand']))->id,
            'name' => $key,
            'slug' => array_fill_keys(config('ribbon.locales'), $key),
            'status' => 'pending',
        ]);
        $product->forceFill(['status' => $status])->save();

        $value = ProductParameterValue::create(['product_id' => $product->id, 'category_parameter_id' => $select->id]);
        ProductParameterValueOption::create(['product_parameter_value_id' => $value->id, 'category_parameter_option_id' => $this->opt[$option]->id]);
        ProductParameterValue::create(['product_id' => $product->id, 'category_parameter_id' => $width->id, 'value_number' => $mm]);

        return $product;
    }

    private function allow(string $type, string $material): void
    {
        RibbonLabelCompatibilityService::sync([$this->opt[$type]->id.':'.$this->opt[$material]->id]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    public function test_ribbon_lists_only_labels_allowed_by_matrix_and_not_wider(): void
    {
        $this->allow('Wax', 'Vellum');

        $ribbon = $this->product($this->ribbons, 'wax-110', $this->type, 'Wax', $this->ribbonWidth, 110);
        $fits = $this->product($this->labels, 'vellum-58', $this->material, 'Vellum', $this->labelWidth, 58);
        $wrongMaterial = $this->product($this->labels, 'pet-58', $this->material, 'PET', $this->labelWidth, 58);
        $tooWide = $this->product($this->labels, 'vellum-150', $this->material, 'Vellum', $this->labelWidth, 150);
        $pending = $this->product($this->labels, 'vellum-40', $this->material, 'Vellum', $this->labelWidth, 40, 'pending');

        $ids = RibbonLabelCompatibilityService::labelsFor($ribbon)->pluck('id')->all();

        $this->assertSame([$fits->id], $ids);
        $this->assertNotContains($wrongMaterial->id, $ids);
        $this->assertNotContains($tooWide->id, $ids);
        $this->assertNotContains($pending->id, $ids);
    }

    public function test_label_lists_fitting_ribbons_as_inverse(): void
    {
        $this->allow('Resin', 'PET');

        $label = $this->product($this->labels, 'pet-100', $this->material, 'PET', $this->labelWidth, 100);
        $fits = $this->product($this->ribbons, 'resin-110', $this->type, 'Resin', $this->ribbonWidth, 110);
        $this->product($this->ribbons, 'resin-60', $this->type, 'Resin', $this->ribbonWidth, 60);
        $this->product($this->ribbons, 'wax-110', $this->type, 'Wax', $this->ribbonWidth, 110);

        $this->assertSame([$fits->id], RibbonLabelCompatibilityService::ribbonsFor($label)->pluck('id')->all());
    }

    public function test_paid_labels_hidden_when_subscription_lapsed(): void
    {
        $this->allow('Wax', 'Vellum');
        $ribbon = $this->product($this->ribbons, 'wax-110', $this->type, 'Wax', $this->ribbonWidth, 110);
        $this->product($this->labels, 'vellum-58', $this->material, 'Vellum', $this->labelWidth, 58);

        $this->seller->forceFill(['subscription_paid_until' => now()->subYear()])->save();

        $this->assertTrue(RibbonLabelCompatibilityService::labelsFor($ribbon->fresh())->isEmpty());
    }

    public function test_empty_matrix_shows_nothing(): void
    {
        $ribbon = $this->product($this->ribbons, 'wax-110', $this->type, 'Wax', $this->ribbonWidth, 110);
        $this->product($this->labels, 'vellum-58', $this->material, 'Vellum', $this->labelWidth, 58);

        $this->assertTrue(RibbonLabelCompatibilityService::labelsFor($ribbon)->isEmpty());
    }

    public function test_sync_drops_pairs_outside_the_coded_parameters(): void
    {
        $foreign = CategoryParameterOption::create([
            'category_parameter_id' => $this->ribbonWidth->id,
            'value' => array_fill_keys(config('ribbon.locales'), 'x'),
            'sort_order' => 9,
        ]);

        RibbonLabelCompatibilityService::sync([
            $this->opt['Wax']->id.':'.$this->opt['Vellum']->id,
            $foreign->id.':'.$this->opt['PET']->id,
            $this->opt['PET']->id.':'.$this->opt['Wax']->id,
            'garbage',
        ]);

        $this->assertSame([$this->opt['Wax']->id.':'.$this->opt['Vellum']->id], RibbonLabelCompatibilityService::pairKeys());
    }

    public function test_super_admin_saves_matrix(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CompatibilityShow::class)
            ->assertOk()
            ->set('pairs', [$this->opt['Resin']->id.':'.$this->opt['PET']->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$this->opt['Resin']->id.':'.$this->opt['PET']->id], RibbonLabelCompatibilityService::pairKeys());
    }

    public function test_moderator_and_seller_cannot_open_or_save(): void
    {
        $role = Role::create(['type' => 'admin', 'name' => 'Mod', 'slug' => 'mod-'.uniqid(), 'is_super_admin' => false]);
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => 'catalog.manage'], ['name' => 'catalog.manage']));
        $moderator = User::factory()->create();
        $moderator->roles()->attach($role);

        $this->actingAs($moderator);
        Livewire::test(CompatibilityShow::class)->assertForbidden();
        $this->get(route('admin.compatibility.show'))->assertForbidden();

        $this->actingAs($this->seller->users()->firstOrFail());
        Livewire::test(CompatibilityShow::class)->assertForbidden();
    }

    public function test_ribbon_product_page_shows_fitting_labels(): void
    {
        $this->allow('Wax', 'Vellum');
        $this->product($this->ribbons, 'wax-110', $this->type, 'Wax', $this->ribbonWidth, 110);
        $this->product($this->labels, 'vellum-58', $this->material, 'Vellum', $this->labelWidth, 58);

        $this->get(route('storefront.products.show', ['productSlug' => 'wax-110']))
            ->assertOk()
            ->assertSee(__('storefront.product_detail.fits_labels_heading'))
            ->assertSee('vellum-58');
    }
}
