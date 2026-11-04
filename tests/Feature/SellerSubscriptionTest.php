<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerSubscriptionAudit;
use App\Models\SellerSubscriptionPayment;
use App\Models\SellerSubscriptionReminder;
use App\Models\Setting;
use App\Models\User;
use App\Services\OfferSelectionService;
use App\Services\SubscriptionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SellerSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubscriptionService
    {
        return app(SubscriptionService::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    private function moderator(): User
    {
        $role = Role::create(['type' => 'admin', 'name' => 'Mod', 'slug' => 'mod-'.uniqid(), 'is_super_admin' => false]);
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => 'products.moderate'], ['name' => 'x']));
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
            ['name' => "Seller $n", 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id, 'city_id' => $city->id, 'vat_number' => "9$n", 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => "o$n@example.test", 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => 'approved', 'subscription_paid_until' => $paidUntil])->save();

        return $seller->fresh();
    }

    private function category(bool $requiresSubscription): Category
    {
        static $n = 0;
        $n++;

        return Category::create([
            'name' => ['uz' => "C$n", 'ru' => "C$n", 'en' => "C$n"],
            'slug' => ['uz' => "c$n-uz", 'ru' => "c$n-ru", 'en' => "c$n-en"],
            'is_active' => true,
            'requires_subscription' => $requiresSubscription,
        ]);
    }

    private function product(Seller $seller, Category $category): Product
    {
        static $n = 0;
        $n++;
        $brand = Brand::first() ?? Brand::create(['name' => 'No Brand']);

        return Product::create([
            'seller_id' => $seller->id, 'category_id' => $category->id, 'brand_id' => $brand->id,
            'name' => "P$n", 'slug' => ['uz' => "p$n-uz", 'ru' => "p$n-ru", 'en' => "p$n-en"], 'status' => 'approved',
        ]);
    }

    private function visible(Product $p): bool
    {
        return Product::query()->visibleOnStorefront()->whereKey($p->id)->exists();
    }

    public function test_visibility_in_all_states(): void
    {
        $paid = $this->category(true);
        $free = $this->category(false);

        $active = $this->product($this->seller(today()->toDateString()), $paid);
        $graceDay7 = $this->product($this->seller(today()->subDays(7)->toDateString()), $paid);
        $lapsedDay8 = $this->product($this->seller(today()->subDays(8)->toDateString()), $paid);
        $never = $this->product($this->seller(null), $paid);
        $freeLapsed = $this->product($this->seller(today()->subDays(30)->toDateString()), $free);

        $this->assertTrue($this->visible($active));
        $this->assertTrue($this->visible($graceDay7));
        $this->assertFalse($this->visible($lapsedDay8));
        $this->assertFalse($this->visible($never));
        $this->assertTrue($this->visible($freeLapsed));

        // Hidden scope is the inverse (for approved products) and statuses are untouched.
        $hidden = Product::query()->hiddenBySubscription()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$lapsedDay8->id, $never->id], $hidden);
        $this->assertSame('approved', $lapsedDay8->fresh()->status);
        $this->assertSame('approved', $lapsedDay8->seller->fresh()->status);
    }

    public function test_grace_days_come_from_settings(): void
    {
        Setting::current()->update(['subscription_grace_days' => 2]);
        $product = $this->product($this->seller(today()->subDays(3)->toDateString()), $this->category(true));

        $this->assertFalse($this->visible($product));

        Setting::current()->update(['subscription_grace_days' => 3]);
        $this->assertTrue($this->visible($product));
    }

    public function test_renewal_restores_visibility(): void
    {
        $seller = $this->seller(today()->subDays(20)->toDateString());
        $product = $this->product($seller, $this->category(true));
        $this->assertFalse($this->visible($product));

        $this->service()->recordPayment($seller, $this->superAdmin(), 500000);

        $this->assertTrue($this->visible($product));
    }

    public function test_early_renewal_extends_from_current_paid_until(): void
    {
        $seller = $this->seller(today()->addDays(10)->toDateString());
        $admin = $this->superAdmin();

        $payment = $this->service()->recordPayment($seller, $admin, 600000, 1, 'bank transfer');

        $expected = today()->addDays(10)->addMonthNoOverflow();
        $this->assertTrue($seller->subscription_paid_until->eq($expected));
        $this->assertTrue($seller->fresh()->subscription_paid_until->eq($expected));
        $this->assertTrue($payment->period_start->eq(today()->addDays(10)));
        $this->assertTrue($payment->period_end->eq($expected));
        $this->assertSame(600000, $payment->amount);
        $this->assertSame($admin->id, $payment->recorded_by);
    }

    public function test_late_renewal_extends_from_today(): void
    {
        $seller = $this->seller(today()->subDays(40)->toDateString());

        $this->service()->recordPayment($seller, $this->superAdmin(), 500000, 2);

        $this->assertTrue($seller->fresh()->subscription_paid_until->eq(today()->addMonthsNoOverflow(2)));

        $never = $this->seller(null);
        $this->service()->recordPayment($never, $this->superAdmin(), 500000);
        $this->assertTrue($never->fresh()->subscription_paid_until->eq(today()->addMonthNoOverflow()));
    }

    public function test_free_months_grant_is_a_zero_amount_payment(): void
    {
        $seller = $this->seller(null);
        $admin = $this->superAdmin();

        $payment = $this->service()->grantFreeMonths($seller, $admin, 3, 'first seller');

        $this->assertSame(0, $payment->amount);
        $this->assertSame(3, $payment->months);
        $this->assertTrue($seller->fresh()->subscription_paid_until->eq(today()->addMonthsNoOverflow(3)));
        $this->assertSame('free_grant', SellerSubscriptionAudit::firstOrFail()->action);
    }

    public function test_audit_rows_are_written_for_payment_and_manual_correction(): void
    {
        $seller = $this->seller(null);
        $admin = $this->superAdmin();

        $this->service()->recordPayment($seller, $admin, 500000, 1, 'inv-1');
        $this->service()->setPaidUntil($seller, $admin, today()->addDays(5), 'typo fix');

        $audits = SellerSubscriptionAudit::orderBy('id')->get();
        $this->assertCount(2, $audits);
        $this->assertSame('payment', $audits[0]->action);
        $this->assertNull($audits[0]->old_paid_until);
        $this->assertSame($admin->id, $audits[0]->user_id);
        $this->assertSame('set_paid_until', $audits[1]->action);
        $this->assertTrue($audits[1]->old_paid_until->eq(today()->addMonthNoOverflow()));
        $this->assertTrue($audits[1]->new_paid_until->eq(today()->addDays(5)));
        $this->assertSame('typo fix', $audits[1]->note);
        $this->assertSame(1, SellerSubscriptionPayment::count());
    }

    public function test_status_helpers_and_seller_panel_data(): void
    {
        $paid = $this->category(true);
        $seller = $this->seller(today()->subDays(3)->toDateString());
        $this->product($seller, $paid);
        $this->product($seller, $this->category(false));
        $s = $this->service();

        $this->assertFalse($s->isActive($seller));
        $this->assertTrue($s->inGrace($seller));
        $this->assertFalse($s->isLapsed($seller));
        $this->assertSame(-3, $s->daysLeft($seller));

        $data = $s->statusFor($seller);
        $this->assertSame('grace', $data['status']);
        $this->assertSame(0, $data['hidden_products_count'], 'grace products are still visible');
        $this->assertSame(-3, $data['days_left']);
    }

    public function test_hidden_count_for_lapsed_seller(): void
    {
        $seller = $this->seller(today()->subDays(20)->toDateString());
        $this->product($seller, $this->category(true));
        $this->product($seller, $this->category(true));
        $this->product($seller, $this->category(false));

        $data = $this->service()->statusFor($seller);

        $this->assertSame('lapsed', $data['status']);
        $this->assertSame(2, $data['hidden_products_count']);
        $this->assertTrue($this->service()->isLapsed($seller));
    }

    public function test_non_super_admin_gets_403(): void
    {
        $seller = $this->seller(null);
        $sellerUser = $seller->users()->first();

        foreach ([$this->moderator(), $sellerUser] as $user) {
            $this->assertTrue(Gate::forUser($user)->denies('manage-subscriptions'));

            foreach ([
                fn () => $this->service()->recordPayment($seller, $user, 1),
                fn () => $this->service()->grantFreeMonths($seller, $user, 1),
                fn () => $this->service()->setPaidUntil($seller, $user, today(), 'x'),
            ] as $attempt) {
                try {
                    $attempt();
                    $this->fail('Expected AuthorizationException');
                } catch (AuthorizationException $e) {
                    $this->assertSame(403, $e->status() ?? 403);
                }
            }
        }

        $this->assertNull($seller->fresh()->subscription_paid_until);
        $this->assertSame(0, SellerSubscriptionAudit::count());
        $this->assertTrue(Gate::forUser($this->superAdmin())->allows('manage-subscriptions'));
    }

    public function test_lapsed_product_is_rejected_from_selection_and_404s(): void
    {
        $seller = $this->seller(today()->subDays(8)->toDateString());
        $product = $this->product($seller, $this->category(true));

        $this->assertFalse(OfferSelectionService::ensure($product->id));
        $this->get('/products/'.$product->slug['uz'])->assertNotFound();

        $this->service()->recordPayment($seller, $this->superAdmin(), 500000);

        $this->assertTrue(OfferSelectionService::ensure($product->id));
        $this->get('/products/'.$product->slug['uz'])->assertOk();
    }

    public function test_reminder_command_is_idempotent(): void
    {
        // Sellers first: registration itself fires a new-seller Telegram notice.
        $this->seller(today()->addDays(3)->toDateString());   // expiring
        $this->seller(today()->subDay()->toDateString());     // grace started
        $this->seller(today()->addDays(20)->toDateString());  // nothing
        $this->seller(today()->subDays(30)->toDateString());  // long lapsed, nothing

        config(['services.telegram.super_admin_chat_id' => '123']);
        Setting::current()->update(['telegram_bot_token' => 'tok', 'subscription_reminder_days' => 3]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->artisan('subscriptions:remind')->assertSuccessful();
        Http::assertSentCount(2);
        $this->assertSame(2, SellerSubscriptionReminder::count());

        $this->artisan('subscriptions:remind')->assertSuccessful();
        $this->artisan('subscriptions:remind')->assertSuccessful();
        Http::assertSentCount(2);
        $this->assertSame(2, SellerSubscriptionReminder::count());
    }

    public function test_reminder_is_retried_when_delivery_fails_and_reopens_after_renewal(): void
    {
        config(['services.telegram.super_admin_chat_id' => null]);
        $seller = $this->seller(today()->addDays(2)->toDateString());

        $this->artisan('subscriptions:remind')->assertSuccessful();
        $this->assertSame(0, SellerSubscriptionReminder::count());

        config(['services.telegram.super_admin_chat_id' => '123']);
        Setting::current()->update(['telegram_bot_token' => 'tok']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->artisan('subscriptions:remind');
        $this->assertSame(1, SellerSubscriptionReminder::count());

        // Renewal moves paid_until, which opens a new reminder period.
        $seller->forceFill(['subscription_paid_until' => today()->addDays(1)])->save();
        $this->artisan('subscriptions:remind');
        $this->assertSame(2, SellerSubscriptionReminder::count());
    }
}
