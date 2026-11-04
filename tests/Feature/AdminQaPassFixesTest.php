<?php

namespace Tests\Feature;

use App\Livewire\Admin\Articles\Index as ArticlesIndex;
use App\Livewire\Admin\Banners\Index as BannersIndex;
use App\Livewire\Admin\Brands\Index as BrandsIndex;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Regressions from admin QA pass #4 (N4-04, 06, 07, 10, 11, 15, 16). */
class AdminQaPassFixesTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $admin;
    }

    #[DataProvider('locales')]
    public function test_validation_messages_are_translated_with_friendly_field_names(string $locale, string $fragment): void
    {
        app()->setLocale($locale);

        $message = Validator::make(['name' => ['uz' => '']], ['name.uz' => 'required'])->errors()->first('name.uz');

        $this->assertStringNotContainsString('validation.', $message);
        $this->assertStringNotContainsString('name.uz', $message);
        $this->assertStringContainsString($fragment, $message);
    }

    public static function locales(): array
    {
        return [['uz', 'Nomi'], ['ru', 'Название'], ['en', 'name']];
    }

    public function test_every_locale_defines_the_required_marker_and_nested_size_rules(): void
    {
        foreach (config('ribbon.locales') as $locale) {
            app()->setLocale($locale);

            $this->assertNotSame('validation.required_mark', __('validation.required_mark'));
            $this->assertNotSame('validation.max.string', __('validation.max.string'));
        }
    }

    #[DataProvider('unsafeLinks')]
    public function test_sanitizer_removes_unsafe_url_schemes(string $html): void
    {
        $this->assertStringNotContainsString('href', HtmlSanitizer::clean($html));
    }

    public static function unsafeLinks(): array
    {
        return [
            'data' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>'],
            'javascript' => ['<a href="javascript:alert(1)">x</a>'],
            'mixed case' => ['<a href="JaVaScRiPt:alert(1)">x</a>'],
            'tab inside scheme' => ["<a href=\"java\tscript:alert(1)\">x</a>"],
            'entity encoded' => ['<a href="&#106;avascript:alert(1)">x</a>'],
            'vbscript' => ['<a href="vbscript:x">x</a>'],
        ];
    }

    #[DataProvider('safeLinks')]
    public function test_sanitizer_keeps_safe_urls(string $url): void
    {
        $this->assertStringContainsString('href', HtmlSanitizer::clean('<a href="'.$url.'">x</a>'));
    }

    public static function safeLinks(): array
    {
        return [['https://ribbon.uz/a'], ['http://ribbon.uz'], ['/articles/a'], ['#top'], ['mailto:a@ribbon.uz'], ['tel:+998901234567'], ['page?x=a:b']];
    }

    public function test_sanitizer_keeps_uploaded_images_but_not_data_svg(): void
    {
        $this->assertStringContainsString('src', HtmlSanitizer::clean('<img src="/storage/a.png" style="float: left">'));
        $this->assertStringContainsString('src', HtmlSanitizer::clean('<img src="data:image/png;base64,AAAA">'));
        $this->assertStringNotContainsString('src', HtmlSanitizer::clean('<img src="data:image/svg+xml;base64,AAAA">'));
    }

    public function test_admin_root_redirects_to_the_landing_page(): void
    {
        $this->actingAs($this->superAdmin())->get('/admin')->assertRedirect('/admin/categories');
    }

    public function test_admin_root_stays_behind_admin_auth(): void
    {
        $this->get('/admin')->assertRedirect();
        $response = $this->actingAs(User::factory()->create())->get('/admin');

        $this->assertNotSame(url('/admin/categories'), $response->headers->get('Location'));
    }

    public function test_brands_page_shows_no_double_escaped_quotes_and_uses_the_shared_dialog(): void
    {
        $brand = Brand::create(['name' => 'Zebra']);

        Livewire::actingAs($this->superAdmin())
            ->test(BrandsIndex::class)
            ->assertDontSeeHtml('&amp;quot;')
            ->assertSeeHtml('aria-haspopup="menu"')
            ->call('confirmDeleteBrand', $brand->id)
            ->assertSeeHtml('role="alertdialog"')
            ->call('deleteBrand');

        $this->assertNull(Brand::find($brand->id));
    }

    public function test_banner_delete_needs_confirmation_and_removes_its_files(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('a.jpg')->store('banners', 'public');

        $banner = Banner::create([
            'title' => array_fill_keys(config('ribbon.locales'), 'Sale'),
            'image_path' => $path,
            'placement' => 'home_hero',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($this->superAdmin())
            ->test(BannersIndex::class)
            ->call('confirmDelete', $banner->id)
            ->assertSeeHtml('role="alertdialog"')
            ->call('cancelDelete');

        $this->assertNotNull($banner->fresh());

        $component->call('confirmDelete', $banner->id)->call('deleteBanner');

        $this->assertNull($banner->fresh());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_article_delete_clears_the_category_guide_and_cover(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('a.jpg')->store('articles', 'public');

        $names = array_fill_keys(config('ribbon.locales'), 'Guide');
        $article = Article::create([
            'type' => 'article',
            'title' => $names,
            'slug' => array_fill_keys(config('ribbon.locales'), 'guide'),
            'body' => $names,
            'cover_image_path' => $path,
        ]);
        $category = Category::create([
            'name' => $names,
            'slug' => array_fill_keys(config('ribbon.locales'), 'guide-cat'),
            'is_active' => true,
            'guide_article_id' => $article->id,
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(ArticlesIndex::class)
            ->call('confirmDelete', $article->id)
            ->call('deleteArticle');

        $this->assertNull($article->fresh());
        $this->assertNull($category->fresh()->guide_article_id);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_actions_are_refused_without_the_content_permission(): void
    {
        $role = Role::create(['type' => 'admin', 'name' => 'Other', 'slug' => 'other-test', 'is_super_admin' => false]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        Livewire::actingAs($user)->test(BannersIndex::class)->assertForbidden();
        Livewire::actingAs($user)->test(ArticlesIndex::class)->assertForbidden();
    }
}
