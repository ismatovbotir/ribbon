<?php

namespace Tests\Feature;

use App\Livewire\Admin\Analytics\Show as AdminAnalytics;
use App\Livewire\Admin\Banners\Index as BannersIndex;
use App\Livewire\Admin\Brands\Index as BrandsIndex;
use App\Livewire\Admin\Categories\Show as CategoriesShow;
use App\Livewire\Admin\Offers\Index as OffersIndex;
use App\Livewire\Admin\Products\Show as AdminProductShow;
use App\Livewire\Admin\Sellers\Show as AdminSellerShow;
use App\Livewire\Admin\Settings\Show as SettingsShow;
use App\Livewire\Admin\Telegram\Show as TelegramShow;
use App\Livewire\Sellers\Employees\Index as EmployeesIndex;
use App\Livewire\Sellers\Products\Edit as ProductEdit;
use App\Livewire\Storefront\Products\Show as ProductShow;
use App\Livewire\Storefront\Search;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryParameter;
use App\Models\CategoryParameterOption;
use App\Models\City;
use App\Models\Country;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\OfferSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorizationGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    /** An admin with a custom role holding only the given permission slugs. */
    private function limitedAdmin(array $permissions = []): User
    {
        $role = Role::create(['type' => 'admin', 'name' => 'Limited', 'slug' => 'limited-'.uniqid(), 'is_super_admin' => false]);

        foreach ($permissions as $slug) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function makeSeller(): Seller
    {
        static $n = 0;
        $n++;
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::first() ?? Country::create(['name' => $names]);
        $region = Region::first() ?? Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::first() ?? City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => "Seller $n", 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id,
                'city_id' => $city->id, 'vat_number' => "200$n", 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => "o$n@example.test", 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => 'approved'])->save();

        return $seller;
    }

    private function ownerOf(Seller $seller): User
    {
        return $seller->users()->firstOrFail();
    }

    private function category(): Category
    {
        return Category::first() ?? Category::create([
            'name' => ['uz' => 'Lentalar', 'ru' => 'Ленты', 'en' => 'Ribbons'],
            'slug' => ['uz' => 'lentalar', 'ru' => 'lenty', 'en' => 'ribbons'],
            'is_active' => true,
            'requires_subscription' => false,
        ]);
    }

    private function product(Seller $seller, string $key = 'p'): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $this->category()->id,
            'brand_id' => (Brand::first() ?? Brand::create(['name' => 'No Brand']))->id,
            'name' => "Ribbon $key",
            'slug' => ['uz' => "$key-uz", 'ru' => "$key-ru", 'en' => "$key-en"],
            'status' => 'pending',
        ]);
    }

    // ---- gates ----

    public function test_gates_follow_role_and_permission_rules(): void
    {
        $super = $this->superAdmin();
        $mod = $this->limitedAdmin(['sellers.moderate']);
        $plain = User::factory()->create();

        $this->assertTrue(Gate::forUser($super)->allows('admin'));
        $this->assertTrue(Gate::forUser($super)->allows('super-admin'));
        $this->assertTrue(Gate::forUser($super)->allows('admin-permission', 'anything'));

        $this->assertTrue(Gate::forUser($mod)->allows('admin'));
        $this->assertFalse(Gate::forUser($mod)->allows('super-admin'));
        $this->assertTrue(Gate::forUser($mod)->allows('admin-permission', 'sellers.moderate'));
        $this->assertFalse(Gate::forUser($mod)->allows('admin-permission', 'products.moderate'));

        $this->assertFalse(Gate::forUser($plain)->allows('admin'));
        $this->assertFalse(Gate::allows('admin'));
    }

    // ---- admin: non-admin / guest ----

    public function test_logged_out_user_gets_403_on_admin_components(): void
    {
        $seller = $this->makeSeller();
        $product = $this->product($seller);

        Livewire::test(AdminSellerShow::class, ['seller' => $seller])->assertForbidden();
        Livewire::test(AdminProductShow::class, ['product' => $product])->assertForbidden();
        Livewire::test(BrandsIndex::class)->assertForbidden();
        Livewire::test(SettingsShow::class)->assertForbidden();
    }

    public function test_seller_user_without_admin_role_gets_403_on_admin_actions(): void
    {
        $seller = $this->makeSeller();
        $product = $this->product($seller);
        $this->actingAs($this->ownerOf($seller));

        Livewire::test(AdminSellerShow::class, ['seller' => $seller])->assertForbidden();
        Livewire::test(AdminProductShow::class, ['product' => $product])->assertForbidden();
        Livewire::test(BannersIndex::class)->assertForbidden();
        $this->assertSame('approved', $seller->fresh()->status);
        $this->assertSame('pending', $product->fresh()->status);
    }

    public function test_admin_action_is_rejected_when_access_is_lost_after_mount(): void
    {
        $seller = $this->makeSeller();
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $component = Livewire::test(AdminProductShow::class, ['product' => $this->product($seller)]);

        // Replayed snapshot after the role was revoked.
        $admin->roles()->detach();
        $component->call('approve')->assertForbidden();
    }

    // ---- admin: non-super admin on super-admin-only sections ----

    public function test_non_super_admin_gets_403_on_super_admin_sections(): void
    {
        $this->actingAs($this->limitedAdmin(['sellers.moderate', 'products.moderate', 'catalog.manage', 'content.manage']));

        Livewire::test(SettingsShow::class)->assertForbidden();
        Livewire::test(OffersIndex::class)->assertForbidden();
        Livewire::test(TelegramShow::class)->assertForbidden();
        Livewire::test(AdminAnalytics::class)->assertForbidden();
    }

    public function test_moderation_requires_the_matching_permission(): void
    {
        $seller = $this->makeSeller();
        $seller->forceFill(['status' => 'pending'])->save();

        $this->actingAs($this->limitedAdmin(['products.moderate']));
        Livewire::test(AdminSellerShow::class, ['seller' => $seller])->assertForbidden();

        $this->actingAs($this->limitedAdmin(['sellers.moderate']));
        Livewire::test(AdminSellerShow::class, ['seller' => $seller])->assertOk()->call('approve');
        $this->assertSame('approved', $seller->fresh()->status);
    }

    public function test_super_admin_can_still_reach_everything(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SettingsShow::class)->assertOk();
        Livewire::test(OffersIndex::class)->assertOk();
        Livewire::test(TelegramShow::class)->assertOk();
        Livewire::test(AdminAnalytics::class)->assertOk();
        Livewire::test(BrandsIndex::class)->assertOk();
    }

    // ---- seller side ----

    public function test_seller_cannot_open_or_act_on_another_sellers_product(): void
    {
        $a = $this->makeSeller();
        $b = $this->makeSeller();
        $productB = $this->product($b, 'b');

        $this->actingAs($this->ownerOf($a));
        $this->get(route('seller.products.edit', $productB))->assertNotFound();

        // Replay: component legitimately mounted by B, then A's session calls an action.
        $this->actingAs($this->ownerOf($b));
        $component = Livewire::test(ProductEdit::class, ['product' => $productB]);
        $this->actingAs($this->ownerOf($a));
        $component->call('saveDetails')->assertForbidden();
    }

    public function test_seller_cannot_target_another_sellers_employee(): void
    {
        $a = $this->makeSeller();
        $b = $this->makeSeller();
        $this->actingAs($this->ownerOf($a));

        Livewire::test(EmployeesIndex::class)
            ->call('confirmRemove', $this->ownerOf($b)->id)
            ->assertNotFound();

        $this->assertTrue($b->users()->whereKey($this->ownerOf($b)->id)->exists());
    }

    public function test_employee_cannot_use_owner_only_employees_component(): void
    {
        $seller = $this->makeSeller();
        $employee = User::factory()->create();
        $seller->users()->attach($employee->id, ['role_id' => Role::where('type', 'seller')->where('slug', 'employee')->value('id')]);
        $this->actingAs($employee);

        Livewire::test(EmployeesIndex::class)->assertForbidden();
    }

    // ---- categories: forged ids ----

    public function test_forged_parameter_ids_from_another_category_are_rejected(): void
    {
        $this->actingAs($this->superAdmin());
        $mine = $this->category();
        $other = Category::create([
            'name' => ['uz' => 'B', 'ru' => 'B', 'en' => 'B'],
            'slug' => ['uz' => 'b', 'ru' => 'b', 'en' => 'b'],
            'is_active' => true,
            'requires_subscription' => false,
        ]);
        $foreign = CategoryParameter::create(['category_id' => $other->id, 'name' => ['uz' => 'F', 'ru' => 'F', 'en' => 'F'], 'type' => 'text', 'sort_order' => 0, 'is_required' => false, 'is_filterable' => true]);

        foreach (['confirmDeleteParameter', 'openEditDrawer'] as $action) {
            Livewire::test(CategoriesShow::class, ['category' => $mine])
                ->call($action, $foreign->id)
                ->assertNotFound();
        }

        $this->assertDatabaseHas('category_parameters', ['id' => $foreign->id]);
    }

    public function test_locked_parameter_id_properties_cannot_be_set_from_client(): void
    {
        $this->actingAs($this->superAdmin());
        $foreign = CategoryParameter::create(['category_id' => $this->category()->id, 'name' => ['uz' => 'F', 'ru' => 'F', 'en' => 'F'], 'type' => 'text', 'sort_order' => 0, 'is_required' => false, 'is_filterable' => true]);

        $component = Livewire::test(CategoriesShow::class, ['category' => $this->category()]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('deletingParameterId', $foreign->id);
    }

    public function test_save_parameter_ignores_option_ids_from_other_parameters(): void
    {
        $this->actingAs($this->superAdmin());
        $category = $this->category();
        $name = ['uz' => 'S', 'ru' => 'S', 'en' => 'S'];
        $mine = CategoryParameter::create(['category_id' => $category->id, 'name' => $name, 'type' => 'select_single', 'sort_order' => 0, 'is_required' => false, 'is_filterable' => true]);
        $other = CategoryParameter::create(['category_id' => $category->id, 'name' => $name, 'type' => 'select_single', 'sort_order' => 1, 'is_required' => false, 'is_filterable' => true]);
        $foreignOption = CategoryParameterOption::create(['category_parameter_id' => $other->id, 'value' => $name, 'sort_order' => 0]);

        $component = Livewire::test(CategoriesShow::class, ['category' => $category])->call('openEditDrawer', $mine->id);
        $component->set('options', [
            ['key' => 'a', 'id' => $foreignOption->id, 'value' => ['uz' => 'HACK', 'ru' => 'HACK', 'en' => 'HACK']],
            ['key' => 'b', 'id' => null, 'value' => $name],
        ])->call('saveParameter');

        $this->assertSame($name, $foreignOption->fresh()->value);
        $this->assertSame($other->id, $foreignOption->fresh()->category_parameter_id);
        $this->assertSame(2, $mine->options()->count());
    }

    // ---- bounded selection ----

    private function visibleProduct(string $key): Product
    {
        $product = $this->product($this->makeSeller(), $key);
        $product->forceFill(['status' => 'approved'])->save();

        return $product;
    }

    public function test_qty_is_clamped(): void
    {
        $product = $this->visibleProduct('q');

        OfferSelectionService::ensure($product->id, null, 9_999_999);
        $this->assertSame(OfferSelectionService::MAX_QTY, OfferSelectionService::currentQty($product->seller_id, $product->id, 'pcs'));

        OfferSelectionService::updateQty($product->seller_id, $product->id, 'pcs', -5);
        $this->assertSame(1, OfferSelectionService::currentQty($product->seller_id, $product->id, 'pcs'));

        OfferSelectionService::updateQty($product->seller_id, $product->id, 'pcs', PHP_INT_MAX);
        $this->assertSame(OfferSelectionService::MAX_QTY, OfferSelectionService::currentQty($product->seller_id, $product->id, 'pcs'));

        OfferSelectionService::add($product, 'pcs', 500);
        $this->assertSame(OfferSelectionService::MAX_QTY, OfferSelectionService::currentQty($product->seller_id, $product->id, 'pcs'));
    }

    public function test_selection_line_cap_rejects_further_adds(): void
    {
        $seller = $this->makeSeller();
        $first = null;

        for ($i = 0; $i < OfferSelectionService::MAX_LINES; $i++) {
            $p = $this->product($seller, "l$i");
            $p->forceFill(['status' => 'approved'])->save();
            $first ??= $p;
            $this->assertTrue(OfferSelectionService::ensure($p->id));
        }

        $this->assertSame(OfferSelectionService::MAX_LINES, OfferSelectionService::count());

        $extra = $this->product($seller, 'extra');
        $extra->forceFill(['status' => 'approved'])->save();

        $this->assertFalse(OfferSelectionService::ensure($extra->id));
        $this->assertSame(OfferSelectionService::MAX_LINES, OfferSelectionService::count());
        // Existing lines stay idempotent and updatable at the cap.
        $this->assertTrue(OfferSelectionService::ensure($first->id));

        Livewire::test(Search::class)
            ->call('quickAdd', $extra->id)
            ->assertHasErrors('selection');
        $this->assertSame(OfferSelectionService::MAX_LINES, OfferSelectionService::count());
    }

    public function test_product_page_qty_survives_non_numeric_input(): void
    {
        $product = $this->visibleProduct('n');
        $slug = $product->slug['uz'];

        Livewire::test(ProductShow::class, ['productSlug' => $slug])
            ->set('qty', 'abc')
            ->assertOk()
            ->call('incrementQty')
            ->assertSet('qty', 2)
            ->set('qty', ['x'])
            ->call('addToRequest')
            ->assertOk()
            ->set('qty', '99999999999')
            ->assertSet('qty', OfferSelectionService::MAX_QTY);
    }

    public function test_selection_mutations_are_rate_limited_per_ip(): void
    {
        $product = $this->visibleProduct('r');
        RateLimiter::clear('offer-selection:ip:127.0.0.1');

        $component = Livewire::test(Search::class);

        for ($i = 0; $i < OfferSelectionService::THROTTLE_PER_MINUTE; $i++) {
            $component->call('quickAdd', $product->id);
        }

        $component->assertHasNoErrors()->call('quickAdd', $product->id)->assertHasErrors('selection');

        RateLimiter::clear('offer-selection:ip:127.0.0.1');
    }
}
