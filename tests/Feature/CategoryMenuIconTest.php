<?php

namespace Tests\Feature;

use App\Livewire\Admin\Categories\Show;
use App\Models\Article;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Spec 22: the admin picks a mega-menu icon and buying guide per category. */
class CategoryMenuIconTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('type', 'admin')->where('slug', 'moderator')->firstOrFail());

        return $user;
    }

    private function category(): Category
    {
        return Category::create([
            'name' => array_fill_keys(config('ribbon.locales'), 'Ribbons'),
            'slug' => array_fill_keys(config('ribbon.locales'), 'ribbons'),
            'is_active' => true,
            'requires_subscription' => false,
        ]);
    }

    private function article(?\DateTimeInterface $publishedAt): Article
    {
        static $n = 0;
        $n++;
        $locales = config('ribbon.locales');

        return Article::create([
            'type' => 'article',
            'title' => array_fill_keys($locales, "Guide $n"),
            'slug' => array_fill_keys($locales, "guide-$n"),
            'excerpt' => array_fill_keys($locales, 'x'),
            'body' => array_fill_keys($locales, '<p>x</p>'),
            'published_at' => $publishedAt,
        ]);
    }

    public function test_moderator_sets_icon_and_published_guide(): void
    {
        $category = $this->category();
        $guide = $this->article(now()->subDay());

        Livewire::actingAs($this->moderator())
            ->test(Show::class, ['category' => $category])
            ->assertSee($guide->title['uz'])
            ->set('catIcon', 'ribbon-roll')
            ->set('catGuideArticleId', $guide->id)
            ->call('saveCategoryDetails')
            ->assertHasNoErrors();

        $category->refresh();
        $this->assertSame('ribbon-roll', $category->icon);
        $this->assertSame($guide->id, $category->guide_article_id);
    }

    public function test_unknown_icon_and_unpublished_guide_are_rejected(): void
    {
        $category = $this->category();
        $draft = $this->article(null);
        $scheduled = $this->article(now()->addDay());

        $component = Livewire::actingAs($this->moderator())->test(Show::class, ['category' => $category])
            ->assertDontSee($draft->title['uz']);

        foreach ([$draft, $scheduled] as $article) {
            $component->set('catIcon', 'product')
                ->set('catGuideArticleId', $article->id)
                ->call('saveCategoryDetails')
                ->assertHasErrors('catGuideArticleId');
        }

        $component->set('catGuideArticleId', null)
            ->set('catIcon', '../../etc/passwd')
            ->call('saveCategoryDetails')
            ->assertHasErrors('catIcon');

        $this->assertNull($category->fresh()->icon);
        $this->assertNull($category->fresh()->guide_article_id);
    }

    public function test_clearing_the_icon_stores_null(): void
    {
        $category = $this->category();
        $category->update(['icon' => 'scanner']);

        Livewire::actingAs($this->moderator())->test(Show::class, ['category' => $category])
            ->assertSet('catIcon', 'scanner')
            ->set('catIcon', '')
            ->call('saveCategoryDetails')
            ->assertHasNoErrors();

        $this->assertNull($category->fresh()->icon);
    }
}
