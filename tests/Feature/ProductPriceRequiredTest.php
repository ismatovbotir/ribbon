<?php

namespace Tests\Feature;

use App\Livewire\Admin\Products\Show as AdminProductShow;
use App\Livewire\Sellers\Products\Create;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** A product can't reach the storefront at 0 UZS (QA N4-02). */
class ProductPriceRequiredTest extends TestCase
{
    use RefreshDatabase;

    private Seller $seller;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);

        $this->seller = Seller::register(
            ['name' => 'Seller', 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id,
                'city_id' => $city->id, 'vat_number' => '2001', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password', 'locale' => 'uz'],
        );
        $this->seller->forceFill(['status' => 'approved'])->save();

        Brand::first() ?? Brand::create(['name' => 'No Brand']);

        $this->category = Category::create([
            'name' => $names,
            'slug' => array_fill_keys(config('ribbon.locales'), 'ribbons'),
            'is_active' => true,
            'requires_subscription' => false,
        ]);
    }

    private function createForm()
    {
        return Livewire::actingAs($this->seller->users()->firstOrFail())
            ->test(Create::class)
            ->set('categoryId', $this->category->id);
    }

    #[DataProvider('invalidPrices')]
    public function test_create_form_rejects_a_missing_or_non_positive_price(?string $price): void
    {
        $this->createForm()
            ->set('pcsPrice', $price)
            ->call('save')
            ->assertHasErrors(['pcsPrice']);

        $this->assertSame(0, Product::count());
    }

    public static function invalidPrices(): array
    {
        return [[null], ['0'], ['-5'], ['abc']];
    }

    public function test_create_form_saves_the_price_on_the_pcs_row(): void
    {
        $this->createForm()
            ->set('pcsPrice', '12500')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::sole();
        $price = $product->prices()->sole();

        $this->assertSame('pending', $product->status);
        $this->assertSame('pcs', $price->unit);
        $this->assertTrue($price->is_vitrin);
        $this->assertEquals(12500, $price->price);
    }

    private function pendingProduct(): Product
    {
        return Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'brand_id' => Brand::first()->id,
            'name' => 'Ribbon',
            'slug' => ['uz' => 'p-uz', 'ru' => 'p-ru', 'en' => 'p-en'],
            'status' => 'pending',
        ]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $admin;
    }

    public function test_moderation_refuses_to_approve_an_unpriced_product(): void
    {
        $product = $this->pendingProduct();

        Livewire::actingAs($this->superAdmin())
            ->test(AdminProductShow::class, ['product' => $product])
            ->call('approve')
            ->assertHasErrors(['approve'])
            ->assertSee(__('admin.products.price_required_to_approve'));

        $this->assertSame('pending', $product->fresh()->status);
        $this->assertSame(0, Product::visibleOnStorefront()->count());
    }

    public function test_moderation_refuses_when_any_enabled_unit_has_no_price(): void
    {
        $product = $this->pendingProduct();
        $product->prices()->where('unit', 'pcs')->firstOrFail()->update(['price' => 1000]);
        $product->prices()->create(['unit' => 'box', 'qty_in_pcs' => 10, 'price' => 0, 'is_vitrin' => false]);

        $this->assertFalse($product->hasCompletePricing());
    }

    public function test_moderation_approves_a_priced_product(): void
    {
        $product = $this->pendingProduct();
        $product->prices()->where('unit', 'pcs')->firstOrFail()->update(['price' => 1000]);

        Livewire::actingAs($this->superAdmin())
            ->test(AdminProductShow::class, ['product' => $product])
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertSame('approved', $product->fresh()->status);
    }

    public function test_the_model_itself_refuses_to_approve_an_unpriced_product(): void
    {
        $product = $this->pendingProduct();

        $this->expectException(LogicException::class);

        $product->approve($this->superAdmin());
    }
}
