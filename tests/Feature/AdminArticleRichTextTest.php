<?php

namespace Tests\Feature;

use App\Livewire\Admin\Articles\Form;
use App\Models\Article;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminArticleRichTextTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());
        Auth::login($admin);

        return $admin;
    }

    private function createCategory(string $slug = 'thermal-transfer-ribbons', string $name = 'Thermal Transfer Ribbons'): Category
    {
        $locales = config('ribbon.locales');

        return Category::create([
            'name' => array_fill_keys($locales, $name),
            'slug' => array_fill_keys($locales, $slug),
            'is_active' => true,
        ]);
    }

    public function test_editor_image_upload_stores_file_and_returns_public_url(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $file = UploadedFile::fake()->image('diagram.png');

        Livewire::test(Form::class)
            ->set('editorImageUpload', $file)
            ->call('storeEditorImage')
            ->assertOk();

        Storage::disk('public')->assertExists('articles/body/'.$file->hashName());
    }

    public function test_save_sanitizes_script_tags_and_event_handlers_out_of_body(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $locales = config('ribbon.locales');
        $malicious = '<p>Safe text</p><script>alert(1)</script><img src=x onerror="alert(1)">';

        $component = Livewire::test(Form::class)->set('type', 'article');

        foreach ($locales as $locale) {
            $component->set("title.{$locale}", "Test title {$locale}")
                ->set("body.{$locale}", $malicious);
        }

        $component->call('save');

        $article = Article::query()->firstOrFail();

        foreach ($locales as $locale) {
            $this->assertStringNotContainsString('<script', $article->body[$locale]);
            $this->assertStringNotContainsString('onerror', $article->body[$locale]);
            $this->assertStringContainsString('Safe text', $article->body[$locale]);
        }
    }

    public function test_admin_can_assign_article_to_several_categories(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $ribbons = $this->createCategory('thermal-transfer-ribbons', 'Thermal Transfer Ribbons');
        $printers = $this->createCategory('tt-printers', 'TT Printers');

        $locales = config('ribbon.locales');
        $component = Livewire::test(Form::class)->set('categoryIds', [(string) $ribbons->id, (string) $printers->id]);

        foreach ($locales as $locale) {
            $component->set("title.{$locale}", "Test title {$locale}")
                ->set("body.{$locale}", '<p>Body</p>');
        }

        $component->call('save');

        $article = Article::query()->firstOrFail();

        $this->assertSame([$ribbons->id, $printers->id], $article->categories()->pluck('categories.id')->sort()->values()->all());
        $this->assertTrue($ribbons->fresh()->articles->contains($article));
        $this->assertTrue($printers->fresh()->articles->contains($article));
    }

    public function test_catalog_page_shows_related_articles_for_each_tagged_category(): void
    {
        $ribbons = $this->createCategory('thermal-transfer-ribbons', 'Thermal Transfer Ribbons');
        $printers = $this->createCategory('tt-printers', 'TT Printers');
        $locales = config('ribbon.locales');
        $defaultLocale = $locales[0];

        $article = Article::factory()->create([
            'title' => array_fill_keys($locales, 'Related Reading Article'),
            'published_at' => now()->subDay(),
        ]);
        $article->categories()->attach([$ribbons->id, $printers->id]);

        foreach ([$ribbons, $printers] as $category) {
            $response = $this->get(route('storefront.catalog.show', ['categorySlug' => $category->slug[$defaultLocale]]));

            $response->assertOk();
            $response->assertSee('Related Reading Article');
        }
    }

    public function test_storefront_renders_article_body_as_raw_html(): void
    {
        $locales = config('ribbon.locales');
        $defaultLocale = $locales[0];

        $article = Article::factory()->create([
            'body' => array_fill_keys($locales, '<p>Hello <strong>world</strong></p>'),
            'published_at' => now()->subDay(),
        ]);

        $slug = $article->slug[$defaultLocale];

        $response = $this->get(route('storefront.articles.show', ['articleSlug' => $slug]));

        $response->assertOk();
        $response->assertSee('Hello <strong>world</strong>', false);
    }
}
