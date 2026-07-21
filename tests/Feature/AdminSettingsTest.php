<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\Show;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // regenerateSitemap() writes a real public/sitemap.xml (see
        // SitemapGeneratorTest) — clean up so runs don't leak a stale file.
        File::delete(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());
        Auth::login($admin);

        return $admin;
    }

    public function test_setting_current_creates_singleton_row_on_first_access(): void
    {
        $this->assertDatabaseCount('settings', 0);

        $setting = Setting::current();

        $this->assertDatabaseCount('settings', 1);
        $this->assertSame(1, $setting->id);
        $this->assertSame($setting->id, Setting::current()->id);
    }

    public function test_super_admin_can_save_settings(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        Livewire::test(Show::class)
            ->set('googleAnalyticsId', 'G-ABC123')
            ->set('yandexMetricaId', '12345678')
            ->set('googleSiteVerification', 'gsc-token')
            ->set('yandexSiteVerification', 'yandex-token')
            ->set('adminPhone', '+998901234567')
            ->set('adminEmail', 'hello@ribbon.uz')
            ->set('defaultMetaDescription', 'Fallback description.')
            ->set('ogImageUpload', UploadedFile::fake()->image('share.png'))
            ->call('save')
            ->assertHasNoErrors();

        $setting = Setting::current();

        $this->assertSame('G-ABC123', $setting->google_analytics_id);
        $this->assertSame('12345678', $setting->yandex_metrica_id);
        $this->assertSame('hello@ribbon.uz', $setting->admin_email);
        $this->assertNotNull($setting->default_og_image_path);
        Storage::disk('public')->assertExists($setting->default_og_image_path);
    }

    public function test_super_admin_can_regenerate_sitemap(): void
    {
        $this->actingAsAdmin();

        $this->assertNull(Setting::current()->sitemap_generated_at);

        Livewire::test(Show::class)
            ->call('regenerateSitemap')
            ->assertHasNoErrors();

        $setting = Setting::current();

        $this->assertNotNull($setting->sitemap_generated_at);
        $this->assertTrue(File::exists(public_path('sitemap.xml')));
    }

    public function test_non_super_admin_cannot_access_settings_page(): void
    {
        $admin = User::factory()->create();
        $nonSuperAdminRole = Role::create(['type' => 'admin', 'name' => 'Moderator', 'slug' => 'moderator', 'is_super_admin' => false]);
        $admin->roles()->attach($nonSuperAdminRole);
        Auth::login($admin);

        $this->get(route('admin.settings.show'))->assertForbidden();
    }

    public function test_storefront_renders_analytics_and_verification_tags_when_configured(): void
    {
        Setting::current()->update([
            'google_analytics_id' => 'G-ABC123',
            'yandex_metrica_id' => '999999',
            'google_site_verification' => 'gsc-token-value',
            'yandex_site_verification' => 'yandex-token-value',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('G-ABC123', false);
        $response->assertSee('999999', false);
        $response->assertSee('gsc-token-value', false);
        $response->assertSee('yandex-token-value', false);
    }

    public function test_storefront_omits_analytics_tags_when_not_configured(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('googletagmanager.com');
        $response->assertDontSee('mc.yandex.ru');
    }

    public function test_storefront_footer_shows_contact_info_when_configured(): void
    {
        Setting::current()->update([
            'admin_phone' => '+998901234567',
            'admin_email' => 'hello@ribbon.uz',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('+998901234567');
        $response->assertSee('hello@ribbon.uz');
    }
}
