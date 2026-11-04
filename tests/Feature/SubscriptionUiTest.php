<?php

namespace Tests\Feature;

use App\Livewire\Admin\Sellers\Show as SellerShow;
use App\Livewire\Admin\Settings\Show as SettingsShow;
use App\Livewire\Sellers\Dashboard;
use App\Livewire\Sellers\Products\Index as ProductsIndex;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionUiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    private function moderator(): User
    {
        $role = Role::create(['type' => 'admin', 'name' => 'Mod', 'slug' => 'mod-'.uniqid(), 'is_super_admin' => false]);
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => 'sellers.moderate'], ['name' => 'x']));
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
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
            ['name' => "UiSeller $n", 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id, 'city_id' => $city->id, 'vat_number' => "8$n", 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => "ui$n@example.test", 'password' => 'password', 'locale' => 'en'],
        );
        $seller->forceFill(['status' => 'approved', 'subscription_paid_until' => $paidUntil])->save();

        return $seller->fresh();
    }

    private function paidProduct(Seller $seller): Product
    {
        static $n = 0;
        $n++;
        $category = Category::create([
            'name' => ['uz' => "PC$n", 'ru' => "PC$n", 'en' => "PC$n"],
            'slug' => ['uz' => "pc$n-uz", 'ru' => "pc$n-ru", 'en' => "pc$n-en"],
            'is_active' => true,
            'requires_subscription' => true,
        ]);

        return Product::create([
            'seller_id' => $seller->id, 'category_id' => $category->id,
            'brand_id' => (Brand::first() ?? Brand::create(['name' => 'B']))->id,
            'name' => "UiP$n", 'slug' => ['uz' => "uip$n-uz", 'ru' => "uip$n-ru", 'en' => "uip$n-en"], 'status' => 'approved',
        ]);
    }

    public function test_moderator_does_not_see_subscription_section_and_actions_are_forbidden(): void
    {
        $seller = $this->seller();
        Auth::login($this->moderator());

        Livewire::test(SellerShow::class, ['seller' => $seller])
            ->assertDontSee(__('admin.subscription.record_payment'))
            ->assertDontSee('id="subscription"', false)
            ->call('openSubscriptionDrawer', 'payment')->assertForbidden();

        Livewire::test(SellerShow::class, ['seller' => $seller])
            ->set('paymentAmount', '100')
            ->call('recordPayment')->assertForbidden();

        Livewire::test(SellerShow::class, ['seller' => $seller])
            ->call('grantFreeMonths')->assertForbidden();

        Livewire::test(SellerShow::class, ['seller' => $seller])
            ->call('correctPaidUntil')->assertForbidden();

        $this->assertNull($seller->fresh()->subscription_paid_until);
    }

    public function test_super_admin_sees_section_and_can_record_payment(): void
    {
        Setting::current()->update(['subscription_price_uzs' => 600000]);
        $seller = $this->seller();
        Auth::login($this->superAdmin());

        Livewire::test(SellerShow::class, ['seller' => $seller])
            ->assertSee(__('admin.subscription.record_payment'))
            ->call('openSubscriptionDrawer', 'payment')
            ->assertSet('paymentAmount', '600000')
            ->assertSet('paymentMonths', '1')
            ->set('paymentNote', 'Bank transfer')
            ->call('recordPayment')
            ->assertHasNoErrors()
            ->assertSet('subscriptionDrawer', '')
            ->assertSee('600 000');

        $this->assertDatabaseHas('seller_subscription_payments', ['seller_id' => $seller->id, 'amount' => 600000, 'months' => 1]);
        $this->assertNotNull($seller->fresh()->subscription_paid_until);
    }

    public function test_subscription_forms_validate_input(): void
    {
        $seller = $this->seller();
        Auth::login($this->superAdmin());

        Livewire::test(SellerShow::class, ['seller' => $seller])
            ->set('paymentAmount', '-5')->set('paymentMonths', '13')
            ->call('recordPayment')->assertHasErrors(['paymentAmount', 'paymentMonths'])
            ->set('freeMonths', '0')
            ->call('grantFreeMonths')->assertHasErrors(['freeMonths'])
            ->set('correctReason', '')
            ->call('correctPaidUntil')->assertHasErrors(['correctReason']);

        $this->assertDatabaseCount('seller_subscription_payments', 0);
    }

    public function test_super_admin_can_grant_free_months_and_correct_date(): void
    {
        $seller = $this->seller();
        Auth::login($this->superAdmin());

        Livewire::test(SellerShow::class, ['seller' => $seller])
            ->set('freeMonths', '2')->call('grantFreeMonths')->assertHasNoErrors()
            ->set('correctDate', '')->set('correctReason', 'Reset')
            ->call('correctPaidUntil')->assertHasNoErrors();

        $this->assertNull($seller->fresh()->subscription_paid_until);
        $this->assertDatabaseHas('seller_subscription_payments', ['seller_id' => $seller->id, 'amount' => 0, 'months' => 2]);
    }

    public function test_settings_save_validates_and_persists_subscription_fields(): void
    {
        Auth::login($this->superAdmin());

        Livewire::test(SettingsShow::class)
            ->set('subscriptionPrice', 'abc')->set('graceDays', '61')->set('reminderDays', '31')
            ->call('save')
            ->assertHasErrors(['subscriptionPrice', 'graceDays', 'reminderDays']);

        Livewire::test(SettingsShow::class)
            ->set('subscriptionPrice', '750000')->set('graceDays', '10')->set('reminderDays', '5')
            ->call('save')->assertHasNoErrors();

        $setting = Setting::current();
        $this->assertSame(750000, $setting->subscription_price_uzs);
        $this->assertSame(10, $setting->subscription_grace_days);
        $this->assertSame(5, $setting->subscription_reminder_days);
    }

    public function test_seller_banner_renders_each_state(): void
    {
        // active, far from expiry: quiet fact
        $active = $this->seller(today()->addDays(30)->toDateString());
        Auth::login($active->users()->first());
        Livewire::test(Dashboard::class)
            ->assertSeeHtml('data-subscription-banner="active"')
            ->assertSee(__('sellers.sub.active', ['date' => today()->addDays(30)->format('d.m.Y')]));

        // active, within reminder window
        $soon = $this->seller(today()->addDays(2)->toDateString());
        Auth::login($soon->users()->first());
        Livewire::test(Dashboard::class)
            ->assertSee(__('sellers.sub.expiring', ['count' => 2, 'date' => today()->addDays(2)->format('d.m.Y')]));

        // grace
        $graceEnds = today()->subDays(2)->addDays(Setting::current()->graceDays());
        $grace = $this->seller(today()->subDays(2)->toDateString());
        Auth::login($grace->users()->first());
        Livewire::test(Dashboard::class)
            ->assertSee(__('sellers.sub.grace', ['date' => $graceEnds->format('d.m.Y')]));

        // lapsed with hidden products
        $lapsed = $this->seller(today()->subDays(60)->toDateString());
        $this->paidProduct($lapsed);
        Auth::login($lapsed->users()->first());
        Livewire::test(Dashboard::class)
            ->assertSee(__('sellers.sub.hidden', ['count' => 1]));

        // none, no hidden products: neutral note
        $none = $this->seller();
        Auth::login($none->users()->first());
        Livewire::test(Dashboard::class)
            ->assertSee(__('sellers.sub.none_note'))
            ->assertDontSee(__('sellers.sub.hidden', ['count' => 0]));

        // lapsed with nothing hidden: no banner content
        $quiet = $this->seller(today()->subDays(60)->toDateString());
        Auth::login($quiet->users()->first());
        Livewire::test(Dashboard::class)
            ->assertDontSee(__('sellers.sub.none_note'))
            ->assertDontSee(__('sellers.sub.hidden_cta'));
    }

    public function test_seller_products_list_flags_hidden_rows(): void
    {
        $seller = $this->seller();
        $this->paidProduct($seller);
        Auth::login($seller->users()->first());

        Livewire::test(ProductsIndex::class)->assertSee(__('sellers.sub.hidden_badge'));

        $seller->forceFill(['subscription_paid_until' => today()->addMonth()])->save();
        Livewire::test(ProductsIndex::class)->assertDontSee(__('sellers.sub.hidden_badge'));
    }
}
