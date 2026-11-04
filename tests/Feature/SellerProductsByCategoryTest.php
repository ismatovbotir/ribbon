<?php

namespace Tests\Feature;

use App\Livewire\Sellers\Products\Create as ProductsCreate;
use App\Livewire\Sellers\Products\Index as ProductsIndex;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use App\Models\Seller;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Seller Products page grouped by category, with paid categories locked for
 * new products when the subscription has lapsed (existing ones stay editable).
 */
class SellerProductsByCategoryTest extends TestCase
{
    use RefreshDatabase;

    private Category $free;

    private Category $paid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->free = $this->category('Ribbons', false, 1);
        $this->paid = $this->category('Printers', true, 2);
    }

    private function category(string $name, bool $paid, int $sort): Category
    {
        $slug = strtolower($name);

        return Category::create([
            'name' => ['uz' => $name, 'ru' => $name, 'en' => $name],
            'slug' => ['uz' => "$slug-uz", 'ru' => "$slug-ru", 'en' => "$slug-en"],
            'is_active' => true,
            'requires_subscription' => $paid,
            'sort_order' => $sort,
        ]);
    }

    private function seller(?string $paidUntil = null): Seller
    {
        static $n = 0;
        $n++;
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::first() ?? Country::create(['name' => $names]);
        $region = Region::first() ?? Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::first() ?? City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => "CatSeller $n", 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id, 'city_id' => $city->id, 'vat_number' => "77$n", 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => "cat$n@example.test", 'password' => 'password', 'locale' => 'en'],
        );
        $seller->forceFill(['status' => 'approved', 'subscription_paid_until' => $paidUntil])->save();

        return $seller->fresh();
    }

    private function product(Seller $seller, Category $category, string $status = 'approved'): Product
    {
        static $n = 0;
        $n++;

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => (Brand::first() ?? Brand::create(['name' => 'B']))->id,
            'name' => "CatP$n",
            'slug' => ['uz' => "catp$n-uz", 'ru' => "catp$n-ru", 'en' => "catp$n-en"],
            'status' => $status,
        ]);
    }

    private function actAs(Seller $seller): void
    {
        Auth::login($seller->users()->first());
    }

    // ---- rule ----

    public function test_can_list_in_follows_subscription_and_grace(): void
    {
        $service = app(SubscriptionService::class);

        $never = $this->seller();
        $active = $this->seller(today()->addMonth()->toDateString());
        $grace = $this->seller(today()->subDays(2)->toDateString());
        $lapsed = $this->seller(today()->subDays(60)->toDateString());

        foreach ([$never, $active, $grace, $lapsed] as $seller) {
            $this->assertTrue($service->canListIn($seller, $this->free), 'free categories are always open');
        }

        $this->assertFalse($service->canListIn($never, $this->paid));
        $this->assertTrue($service->canListIn($active, $this->paid));
        $this->assertTrue($service->canListIn($grace, $this->paid));
        $this->assertFalse($service->canListIn($lapsed, $this->paid));
    }

    // ---- index ----

    public function test_lists_all_categories_and_locks_paid_ones_for_unpaid_seller(): void
    {
        $seller = $this->seller();
        $this->actAs($seller);

        $html = Livewire::test(ProductsIndex::class)
            ->assertSee('Ribbons')
            ->assertSee('Printers')
            ->assertSee(__('sellers.products.index.locked_badge'))
            ->html();

        $this->assertStringContainsString('seller/products/create?category='.$this->free->id, $html);
        $this->assertStringNotContainsString('seller/products/create?category='.$this->paid->id, $html);
    }

    public function test_paying_seller_gets_add_in_every_category(): void
    {
        $this->actAs($this->seller(today()->addMonth()->toDateString()));

        $html = Livewire::test(ProductsIndex::class)
            ->assertDontSee(__('sellers.products.index.locked_badge'))
            ->html();

        $this->assertStringContainsString('seller/products/create?category='.$this->paid->id, $html);
    }

    public function test_opens_first_category_with_products_and_toggles(): void
    {
        $seller = $this->seller(today()->addMonth()->toDateString());
        $printer = $this->product($seller, $this->paid);
        $this->actAs($seller);

        Livewire::test(ProductsIndex::class)
            ->assertSee($printer->name)
            ->call('toggle', $this->free->id)
            ->assertSet('open', $this->free->id)
            ->assertDontSee($printer->name)
            ->assertSee(__('sellers.products.index.category_empty'))
            ->call('toggle', $this->free->id)
            ->assertSet('open', 0);

        // Last opened category is remembered for the next visit.
        $this->assertSame($this->free->id, session('seller.products.open_category'));
    }

    public function test_locked_category_still_shows_existing_products(): void
    {
        $seller = $this->seller(today()->subDays(60)->toDateString());
        $old = $this->product($seller, $this->paid);
        $this->actAs($seller);

        Livewire::test(ProductsIndex::class, ['open' => $this->paid->id])
            ->assertSee($old->name)
            ->assertSee(__('sellers.products.index.locked_title'));
    }

    public function test_counts_ignore_other_sellers_and_show_attention_badges(): void
    {
        $seller = $this->seller();
        $this->product($seller, $this->free, 'pending');
        $this->product($seller, $this->free, 'rejected');
        $this->product($this->seller(), $this->free, 'rejected');
        $this->actAs($seller);

        Livewire::test(ProductsIndex::class)
            ->assertSee(__('sellers.products.index.pending_count', ['count' => 1]))
            ->assertSee(__('sellers.products.index.rejected_count', ['count' => 1]))
            ->assertDontSee(__('sellers.products.index.rejected_count', ['count' => 2]));
    }

    // ---- create ----

    public function test_create_from_category_fixes_the_category(): void
    {
        $this->actAs($this->seller());

        Livewire::withQueryParams(['category' => $this->free->id])
            ->test(ProductsCreate::class)
            ->assertSet('categoryId', $this->free->id)
            ->assertSet('categoryFixed', true)
            ->assertSee(__('sellers.products.create.category_change'));
    }

    public function test_fixed_category_cannot_be_swapped_from_client(): void
    {
        $this->actAs($this->seller(today()->addMonth()->toDateString()));

        Livewire::withQueryParams(['category' => $this->free->id])
            ->test(ProductsCreate::class)
            ->set('categoryId', $this->paid->id)
            ->assertForbidden();
    }

    public function test_locked_category_link_falls_back_to_picker_with_error(): void
    {
        $this->actAs($this->seller());

        Livewire::withQueryParams(['category' => $this->paid->id])
            ->test(ProductsCreate::class)
            ->assertSet('categoryFixed', false)
            ->assertSet('categoryId', null)
            ->assertHasErrors(['categoryId']);
    }

    public function test_saving_in_paid_category_without_subscription_is_rejected(): void
    {
        $seller = $this->seller();
        $this->actAs($seller);

        Livewire::test(ProductsCreate::class)
            ->set('categoryId', $this->paid->id)
            ->call('save')
            ->assertHasErrors(['categoryId']);

        $this->assertSame(0, $seller->products()->count());
    }

    public function test_picker_disables_locked_categories(): void
    {
        $this->actAs($this->seller());

        $component = Livewire::test(ProductsCreate::class);

        $this->assertSame([$this->paid->id], $component->instance()->lockedCategoryIds);
        $component->assertSee(__('sellers.products.create.category_locked_suffix'));
    }
}
