<?php

namespace Tests\Feature;

use App\Livewire\Storefront\Contact;
use App\Livewire\Storefront\OfferRequest\Show as OfferRequestShow;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression tests for the fixes from QA report
 * .claude/docs/qa/2026-11-02-baseline.md (F-01, F-02, F-03, F-05, F-06).
 */
class QaBaselineFixesTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(): Seller
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => 'QA Seller', 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id,
                'city_id' => $city->id, 'vat_number' => '200100', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => 'qa-owner@example.test', 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => 'approved'])->save();

        return $seller;
    }

    // ---- F-01: phone validation ----

    public static function phones(): array
    {
        return [
            'uz international' => ['+998 90 123-45-67', true],
            'uz digits only' => ['998901234567', true],
            'ru with parens' => ['+7 (912) 345-67-89', true],
            'kz' => ['+7 701 234 5678', true],
            'free text' => ['QA test not a phone', false],
            'letters mixed in' => ['+998 90 CALL ME', false],
            'too short' => ['12345', false],
            'too long' => ['+1234567890123456', false],
            'punctuation only' => ['+() -', false],
        ];
    }

    #[DataProvider('phones')]
    public function test_phone_rule($phone, bool $valid): void
    {
        $this->assertSame($valid, PhoneNumber::isValid($phone));
    }

    public function test_offer_request_rejects_non_phone_text(): void
    {
        Livewire::test(OfferRequestShow::class)
            ->set('phone', 'QA test not a phone')
            ->tap(fn () => $this->travel(10)->seconds())
            ->call('submit')
            ->assertHasErrors(['phone'])
            ->assertSet('submitted', false);
    }

    public function test_contact_form_rejects_non_phone_text(): void
    {
        Livewire::test(Contact::class)
            ->set('phone', 'not a phone')
            ->tap(fn () => $this->travel(10)->seconds())
            ->call('submit')
            ->assertHasErrors(['phone']);
    }

    // ---- F-02: cross-panel URLs return 403 and keep the session ----

    public function test_seller_opening_admin_url_gets_403_and_stays_logged_in(): void
    {
        $owner = $this->approvedSeller()->users()->firstOrFail();

        $this->actingAs($owner)->get('/admin/sellers')->assertForbidden();
        $this->assertAuthenticatedAs($owner);
    }

    public function test_admin_opening_seller_url_gets_403_and_stays_logged_in(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        $this->actingAs($admin)->get('/seller/analytics')->assertForbidden();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_user_without_any_access_is_still_logged_out_on_admin_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/sellers')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_suspended_seller_is_still_logged_out_on_seller_url(): void
    {
        $seller = $this->approvedSeller();
        $owner = $seller->users()->firstOrFail();
        $seller->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($owner)->get('/seller/analytics')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ---- F-03, F-05, F-06: markup ----

    public function test_login_and_register_labels_point_at_their_inputs(): void
    {
        $login = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('for="field-email"', $login);
        $this->assertStringContainsString('id="field-email"', $login);
        $this->assertStringContainsString('for="field-password"', $login);
        $this->assertStringContainsString('id="field-password"', $login);

        $register = $this->get('/sellers/register')->assertOk()->getContent();
        foreach (['ownerName', 'ownerEmail', 'ownerPassword', 'ownerPasswordConfirmation', 'locale'] as $field) {
            $this->assertStringContainsString('for="field-'.$field.'"', $register);
            $this->assertStringContainsString('id="field-'.$field.'"', $register);
        }
    }

    public function test_register_title_is_localised(): void
    {
        app()->setLocale('ru');
        $this->get('/sellers/register?lang=ru')
            ->assertOk()
            ->assertSee('<title>'.e(__('storefront.nav.become_seller', [], 'ru')), false)
            ->assertDontSee('<title>Become a Seller', false);
    }

    public function test_offer_request_page_is_noindex(): void
    {
        $this->get('/request')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }
}
