<?php

namespace Tests\Feature;

use App\Livewire\Admin\Banners\Form;
use App\Models\Role;
use App\Models\User;
use App\Rules\SafeLinkUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Stored XSS through a banner link (QA N4-01). */
class BannerLinkSafetyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('links')]
    public function test_only_http_and_site_relative_links_are_allowed(string $url, bool $valid): void
    {
        $this->assertSame($valid, SafeLinkUrl::isValid($url));
    }

    public static function links(): array
    {
        return [
            ['https://ribbon.uz/promo', true],
            ['HTTP://ribbon.uz', true],
            ['/catalog/ribbons?x=1', true],
            ['javascript:alert(1)', false],
            ['JaVaScRiPt:alert(1)', false],
            [' javascript:alert(1)', false],
            ["java\nscript:alert(1)", false],
            ['data:text/html,<script>alert(1)</script>', false],
            ['vbscript:x', false],
            ['//evil.example', false],
            ['/\\evil.example', false],
            ["https://ribbon.uz\n", false],
            ['catalog/ribbons', false],
        ];
    }

    public function test_admin_form_rejects_a_javascript_link(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        Livewire::actingAs($admin)->test(Form::class)
            ->set('linkUrl', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors(['linkUrl']);
    }

    public function test_an_unsafe_link_already_in_the_database_does_not_render_as_a_link(): void
    {
        $render = fn (string $href) => Blade::render('<x-storefront.banner-frame :href="$href">x</x-storefront.banner-frame>', ['href' => $href]);

        $this->assertStringNotContainsString('<a', $render('javascript:alert(1)'));
        $this->assertStringContainsString('<a href="/catalog"', $render('/catalog'));
    }
}
