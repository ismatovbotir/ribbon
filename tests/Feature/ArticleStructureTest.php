<?php

namespace Tests\Feature;

use App\Livewire\Admin\Articles\Form;
use App\Models\Article;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RibbonGuideSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleStructureTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $admin;
    }

    private function form(string $type = 'article')
    {
        $text = fn (string $value) => array_fill_keys(config('ribbon.locales'), $value);

        return Livewire::actingAs($this->admin())
            ->test(Form::class)
            ->set('type', $type)
            ->set('title', $text('Title'))
            ->set('body', $text('<p>Body</p>'));
    }

    private function published(array $attributes = []): Article
    {
        $text = fn (string $value) => array_fill_keys(config('ribbon.locales'), $value);

        return Article::create($attributes + [
            'type' => 'article',
            'title' => $text('Guide'),
            'slug' => $text('guide'),
            'body' => $text('<p>Body</p>'),
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_guides_need_a_short_answer_and_news_does_not(): void
    {
        $this->form()->call('save')->assertHasErrors(['shortAnswer.uz', 'shortAnswer.ru', 'shortAnswer.en']);

        $this->form('news')->call('save')->assertHasNoErrors();
    }

    public function test_short_answer_and_faq_are_saved_per_locale(): void
    {
        $text = array_fill_keys(config('ribbon.locales'), 'Direct answer');

        $component = $this->form()->set('shortAnswer', $text)->call('addFaq', 'ru');
        $component->set('faq.ru.0.q', 'Вопрос?')->set('faq.ru.0.a', 'Ответ.')
            ->set('faq.xx', [['q' => 'injected', 'a' => 'injected']])
            ->call('save')->assertHasNoErrors();

        $article = Article::sole();
        $this->assertSame('Direct answer', $article->short_answer['ru']);
        $this->assertSame([['q' => 'Вопрос?', 'a' => 'Ответ.']], $article->faq['ru']);
        $this->assertSame([], $article->faq['en']);
        $this->assertArrayNotHasKey('xx', $article->faq);
    }

    public function test_an_unfinished_faq_item_is_rejected_and_can_be_removed(): void
    {
        $text = array_fill_keys(config('ribbon.locales'), 'Direct answer');

        $component = $this->form()->set('shortAnswer', $text)->call('addFaq', 'en')->call('save')->assertHasErrors(['faq.en.0.q', 'faq.en.0.a']);

        $component->call('removeFaq', 'en', 0)->call('save')->assertHasNoErrors();
    }

    public function test_faq_length_is_capped(): void
    {
        $component = $this->form();

        foreach (range(1, 10) as $i) {
            $component->call('addFaq', 'uz');
        }

        $this->assertCount(8, $component->get('faq')['uz']);
    }

    public function test_article_page_shows_the_answer_faq_products_and_faq_markup_escaped(): void
    {
        $text = fn (mixed $value) => array_fill_keys(config('ribbon.locales'), $value);
        $article = $this->published([
            'short_answer' => $text('Direct <b>answer</b>'),
            'faq' => $text([['q' => 'Which ribbon?', 'a' => 'It depends.']]),
        ]);
        $category = Category::create([
            'name' => $text('Ribbons'), 'slug' => $text('ribbons'), 'is_active' => true, 'requires_subscription' => false,
        ]);
        $article->categories()->attach($category);

        $html = $this->get('/articles/guide')->assertOk()
            ->assertSee('Direct &lt;b&gt;answer&lt;/b&gt;', false)
            ->assertSee('Which ribbon?')
            ->assertSee(__('storefront.articles.see_products'))
            ->getContent();

        $this->assertStringContainsString('"FAQPage"', $html);
    }

    public function test_an_article_without_the_new_fields_still_renders(): void
    {
        $this->published();

        $html = $this->get('/articles/guide')->assertOk()->getContent();

        $this->assertStringNotContainsString('"FAQPage"', $html);
    }

    public function test_the_guide_seeds_and_every_diagram_exists(): void
    {
        $this->seed(RibbonGuideSeeder::class);

        $article = Article::where('slug->en', 'thermal-transfer-ribbon-guide')->sole();

        $this->assertNotEmpty($article->short_answer['ru']);
        $this->assertNotEmpty($article->faq['ru']);

        preg_match_all('#/images/guides/[a-z-]+\.svg#', $article->body['ru'], $images);
        $this->assertCount(4, $images[0]);

        foreach ($images[0] as $path) {
            $this->assertFileExists(public_path($path));
        }
    }

    public function test_the_guide_never_claims_unverified_things(): void
    {
        $this->seed(RibbonGuideSeeder::class);

        $body = Article::where('slug->en', 'thermal-transfer-ribbon-guide')->sole()->body['ru'];

        $this->assertStringNotContainsString('1,5 дюйма', $body);
        $this->assertStringNotContainsString('подделк', mb_strtolower($body));
    }

    public function test_every_storefront_page_has_the_same_single_header_search(): void
    {
        $this->published();

        foreach (['/', '/articles', '/articles/guide', '/contact'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('id="sf-search-d"', $html, $url);
            $this->assertStringContainsString('id="sf-search-m"', $html, $url);
            $this->assertStringNotContainsString('id="home-search"', $html, $url);
        }
    }
}
