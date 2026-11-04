<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\DemoStaffSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Moderator (CLAUDE.md "Roles"): moderation + catalog/content, nothing that
 * exposes buyer PII, settings, platform analytics, subscriptions or the
 * Telegram inbox.
 */
class ModeratorRoleTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('type', 'admin')->where('slug', 'moderator')->firstOrFail());

        return $user;
    }

    public function test_seeder_creates_moderator_with_exact_permissions_and_is_idempotent(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        $roles = Role::where('type', 'admin')->where('slug', 'moderator')->get();
        $this->assertCount(1, $roles);
        $this->assertFalse((bool) $roles->first()->is_super_admin);
        $this->assertEqualsCanonicalizing(
            ['sellers.moderate', 'products.moderate', 'catalog.manage', 'content.manage', 'pages.manage'],
            $roles->first()->permissions()->pluck('slug')->all(),
        );
    }

    public function test_moderator_reaches_moderation_catalog_and_content(): void
    {
        $this->actingAs($this->moderator());

        foreach (['/admin/sellers', '/admin/products', '/admin/categories', '/admin/brands', '/admin/banners', '/admin/articles'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_moderator_is_kept_out_of_super_admin_areas(): void
    {
        $this->actingAs($this->moderator());

        foreach (['/admin/offers', '/admin/settings', '/admin/messages', '/admin/analytics', '/admin/contact-messages'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_moderator_cannot_manage_subscriptions(): void
    {
        $this->assertFalse(Gate::forUser($this->moderator())->allows('manage-subscriptions'));
    }

    public function test_demo_staff_seeder_creates_a_login_for_every_actor_in_local_only(): void
    {
        $this->seed(PermissionSeeder::class);
        $seller = $this->demoSeller();

        // Not local: nothing is created.
        $this->seed(DemoStaffSeeder::class);
        $this->assertDatabaseMissing('users', ['email' => 'moderator@demo.ribbon.uz']);

        $this->app['env'] = 'local';
        $this->seed(DemoStaffSeeder::class);
        $this->seed(DemoStaffSeeder::class); // idempotent

        $admin = User::where('email', 'admin@demo.ribbon.uz')->firstOrFail();
        $moderator = User::where('email', 'moderator@demo.ribbon.uz')->firstOrFail();
        $employee = User::where('email', 'employee@demo.ribbon.uz')->firstOrFail();

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($moderator->isAdmin());
        $this->assertFalse($moderator->isSuperAdmin());
        $this->assertTrue(Hash::check(DemoStaffSeeder::PASSWORD, $moderator->password));
        $this->assertSame($seller->id, $employee->sellerOrFail()->id);
        $this->assertFalse($employee->isOwnerOf($seller));
        $this->assertSame(1, $seller->users()->where('email', 'employee@demo.ribbon.uz')->count());
    }

    private function demoSeller(): Seller
    {
        $names = array_fill_keys(config('ribbon.locales'), 'X');
        $country = Country::create(['name' => $names]);
        $region = Region::create(['country_id' => $country->id, 'name' => $names]);
        $city = City::create(['region_id' => $region->id, 'name' => $names]);

        $seller = Seller::register(
            ['name' => 'Demo', 'address' => 'A', 'country_id' => $country->id, 'region_id' => $region->id, 'city_id' => $city->id, 'vat_number' => '301234567', 'phone' => '+998901234567'],
            ['name' => 'Owner', 'email' => 'seller1@demo.ribbon.uz', 'password' => 'password', 'locale' => 'uz'],
        );
        $seller->forceFill(['status' => 'approved'])->save();

        return $seller;
    }
}
