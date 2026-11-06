<?php

namespace Tests\Feature;

use App\Livewire\Admin\Sellers\Show as AdminSellerShow;
use App\Livewire\Sellers\Products\Create;
use App\Livewire\Sellers\Products\Edit;
use App\Livewire\Sellers\Profile\Index as Profile;
use App\Livewire\Sellers\Register;
use App\Livewire\Storefront\OfferRequest\Show as OfferRequestShow;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\OfferRequest;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\OfferSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/** MVP gaps from admin QA pass #4 (N4-14). */
class MvpBuyerAndSellerInfoTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Region $region;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $this->country = Country::create(['name' => $names]);
        $this->region = Region::create(['country_id' => $this->country->id, 'name' => $names]);
        $this->city = City::create(['region_id' => $this->region->id, 'name' => $names]);
    }

    private function seller(string $email = 'owner@example.test'): Seller
    {
        $seller = Seller::register(
            ['name' => 'Seller', 'address' => 'A', 'country_id' => $this->country->id, 'region_id' => $this->region->id,
                'city_id' => $this->city->id, 'vat_number' => '200123456', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => $email, 'password' => 'password', 'locale' => 'uz'],
        );

        $seller->forceFill(['status' => 'approved'])->save();

        return $seller;
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $admin;
    }

    private function adminWith(string ...$permissions): User
    {
        $role = Role::create(['type' => 'admin', 'name' => 'R', 'slug' => 'r-'.uniqid(), 'is_super_admin' => false]);

        foreach ($permissions as $slug) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function product(Seller $seller, array $attributes = []): Product
    {
        Brand::first() ?? Brand::create(['name' => 'No Brand']);

        $category = Category::create([
            'name' => array_fill_keys(config('ribbon.locales'), 'Ribbons'),
            'slug' => array_fill_keys(config('ribbon.locales'), 'ribbons-'.uniqid()),
            'is_active' => true,
            'requires_subscription' => false,
        ]);

        $product = Product::create($attributes + [
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => Brand::first()->id,
            'name' => 'Ribbon',
            'slug' => ['uz' => 'p-uz', 'ru' => 'p-ru', 'en' => 'p-en'],
            'status' => 'approved',
        ]);
        $product->prices()->where('unit', 'pcs')->firstOrFail()->update(['price' => 1000]);

        return $product;
    }

    // ---- Offer request: buyer type + attribution ----

    private function submitOffer(array $set = [])
    {
        $seller = $this->seller();
        OfferSelectionService::add($this->product($seller), 'pcs', 1);

        $component = Livewire::test(OfferRequestShow::class)->set('phone', '+998901112233');

        foreach ($set as $key => $value) {
            $component->set($key, $value);
        }

        $this->travel(5)->seconds();

        return $component->call('submit');
    }

    public function test_buyer_type_is_optional_and_saved(): void
    {
        $this->submitOffer(['buyerType' => 'logistics'])->assertHasNoErrors();

        $this->assertSame('logistics', OfferRequest::sole()->buyer_type);
    }

    public function test_offer_can_be_sent_without_a_buyer_type(): void
    {
        $this->submitOffer()->assertHasNoErrors();

        $this->assertNull(OfferRequest::sole()->buyer_type);
    }

    public function test_unknown_buyer_type_is_rejected(): void
    {
        $this->submitOffer(['buyerType' => 'hacker'])->assertHasErrors(['buyerType']);
    }

    public function test_landing_page_attribution_survives_browsing_to_the_request(): void
    {
        $this->withHeader('referer', 'https://t.me/ribbon?secret=abc')
            ->get('/?utm_source=telegram&utm_campaign=launch');

        $this->withHeader('referer', url('/catalog'))->get('/contact');

        $captured = session('attribution');
        $this->assertSame('telegram', $captured['utm']['utm_source']);
        $this->assertSame('launch', $captured['utm']['utm_campaign']);
        $this->assertSame('https://t.me/ribbon', $captured['referrer']);
    }

    public function test_internal_navigation_is_not_recorded_as_a_referrer(): void
    {
        $this->withHeader('referer', url('/catalog'))->get('/contact');

        $this->assertNull(session('attribution'));
    }

    public function test_session_attribution_is_stored_on_the_offer(): void
    {
        session(['attribution' => ['utm' => ['utm_source' => 'google'], 'referrer' => 'https://google.com/']]);

        $this->submitOffer()->assertHasNoErrors();

        $offer = OfferRequest::sole();
        $this->assertSame('google', $offer->utm['utm_source']);
        $this->assertSame('https://google.com/', $offer->referrer);
    }

    public function test_attribution_cannot_be_changed_by_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(OfferRequestShow::class)->set('referrer', 'https://evil.test');
    }

    // ---- MOQ ----

    public function test_create_form_saves_moq_and_rejects_invalid_values(): void
    {
        $seller = $this->seller();
        $category = $this->product($seller)->category;

        $form = fn ($moq) => Livewire::actingAs($seller->users()->firstOrFail())
            ->test(Create::class)
            ->set('categoryId', $category->id)
            ->set('pcsPrice', '1000')
            ->set('moqPcs', $moq)
            ->call('save');

        $form('0')->assertHasErrors(['moqPcs']);
        $form('abc')->assertHasErrors(['moqPcs']);
        $form('25')->assertHasNoErrors();

        $this->assertSame(25, Product::latest('id')->first()->moqInPcs());
    }

    public function test_edit_form_loads_and_saves_moq(): void
    {
        $seller = $this->seller();
        $product = $this->product($seller, ['moq_pcs' => 10]);

        Livewire::actingAs($seller->users()->firstOrFail())
            ->test(Edit::class, ['product' => $product])
            ->assertSet('moqPcs', 10)
            ->set('moqPcs', 50)
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->assertSame(50, $product->fresh()->moqInPcs());
    }

    public function test_minimum_quantity_per_unit_follows_moq(): void
    {
        $product = $this->product($this->seller(), ['moq_pcs' => 25]);
        $box = $product->prices()->create(['unit' => 'box', 'qty_in_pcs' => 10, 'price' => 5000, 'is_vitrin' => false]);

        $this->assertSame(25, $product->minQtyForPrice($product->prices()->where('unit', 'pcs')->first()));
        $this->assertSame(3, $product->minQtyForPrice($box));
    }

    // ---- Registration and tax number ----

    private function registerForm()
    {
        return Livewire::test(Register::class)
            ->set('ownerName', 'New Owner')
            ->set('ownerEmail', 'new@example.test')
            ->set('ownerPassword', 'password123')
            ->set('ownerPasswordConfirmation', 'password123')
            ->set('locale', 'uz')
            ->set('companyName', 'New Co')
            ->set('countryId', $this->country->id)
            ->set('regionId', $this->region->id)
            ->set('cityId', $this->city->id)
            ->set('companyAddress', 'Addr')
            ->set('companyVatNumber', '300123456')
            ->set('companyPhone', '+998901234567');
    }

    public function test_registration_needs_no_certificate(): void
    {
        $this->registerForm()->call('register')->assertHasNoErrors();

        $this->assertSame('pending', Seller::sole()->refresh()->status);
    }

    public function test_tax_number_must_be_9_to_14_digits(): void
    {
        foreach (['12345678', '123456789012345', '12345abcd', '123 456 789', '+123456789'] as $invalid) {
            $this->registerForm()->set('companyVatNumber', $invalid)->call('register')->assertHasErrors(['companyVatNumber']);
        }

        $this->assertSame(0, Seller::count());

        $this->registerForm()->set('companyVatNumber', '12345678901234')->call('register')->assertHasNoErrors();

        $this->assertSame('12345678901234', Seller::sole()->vat_number);
    }

    public function test_a_pending_seller_is_approved_with_only_a_tax_number(): void
    {
        $seller = $this->seller();
        $seller->forceFill(['status' => 'pending'])->save();

        Livewire::actingAs($this->superAdmin())
            ->test(AdminSellerShow::class, ['seller' => $seller])
            ->assertSee(__('admin.sellers.approval_check'))
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertSame('approved', $seller->fresh()->status);
    }

    public function test_buyers_are_told_what_is_actually_checked(): void
    {
        foreach (config('ribbon.locales') as $locale) {
            app()->setLocale($locale);
            $text = mb_strtolower(__('storefront.seller.verified_explainer'));

            $this->assertStringNotContainsString('registration', $text);
            $this->assertStringNotContainsString('регистрац', $text);
            $this->assertStringNotContainsString('o\'tishi', $text);
        }
    }

    // ---- Seller B2B info ----

    private function employeeOf(Seller $seller): User
    {
        return $seller->addEmployee(['name' => 'E', 'email' => 'e@example.test', 'password' => 'password', 'locale' => 'uz']);
    }

    public function test_owner_saves_business_info(): void
    {
        $seller = $this->seller();

        Livewire::actingAs($seller->users()->firstOrFail())
            ->test(Profile::class)
            ->set('vatPayer', 'yes')
            ->set('paymentMethods', ['transfer', 'card'])
            ->set('warrantyInfo', '12 months')
            ->call('saveBusinessInfo')
            ->assertHasNoErrors();

        $seller->refresh();
        $this->assertTrue($seller->is_vat_payer);
        $this->assertSame(['transfer', 'card'], $seller->payment_methods);
        $this->assertSame('12 months', $seller->warranty_info);
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        $seller = $this->seller();

        Livewire::actingAs($seller->users()->firstOrFail())
            ->test(Profile::class)
            ->set('paymentMethods', ['bitcoin'])
            ->call('saveBusinessInfo')
            ->assertHasErrors(['paymentMethods.0']);
    }

    public function test_employee_cannot_change_business_info(): void
    {
        $seller = $this->seller();
        $employee = $this->employeeOf($seller);

        Livewire::actingAs($employee)->test(Profile::class)
            ->set('vatPayer', 'yes')
            ->call('saveBusinessInfo')
            ->assertForbidden();

        $this->assertNull($seller->fresh()->is_vat_payer);
    }

    public function test_product_page_shows_business_info_and_escapes_warranty_text(): void
    {
        $seller = $this->seller();
        $seller->update(['is_vat_payer' => true, 'payment_methods' => ['cash'], 'warranty_info' => '<script>alert(1)</script>']);
        $product = $this->product($seller);
        app()->setLocale('en');

        $this->get('/products/'.$product->slug['uz'].'?lang=uz')
            ->assertOk()
            ->assertSee(__('storefront.seller.vat_payer', [], 'uz'))
            ->assertSee(__('storefront.seller.payment_method.cash', [], 'uz'))
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
