<?php

namespace Tests\Feature;

use App\Livewire\Admin\Categories\Index;
use App\Livewire\Admin\Categories\Show;
use App\Models\Category;
use App\Models\CategorySubscriptionAudit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** A category's free/paid flag decides who must pay: Super Admin only (QA N4-03). */
class CategoryFreeFlagAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        $role = Role::create(['type' => 'admin', 'name' => 'Mod', 'slug' => 'mod-test', 'is_super_admin' => false]);
        $role->permissions()->attach(Permission::firstOrCreate(['slug' => 'catalog.manage'], ['name' => 'catalog.manage']));

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    private function names(string $value = 'Printers'): array
    {
        return array_fill_keys(config('ribbon.locales'), $value);
    }

    private function paidCategory(): Category
    {
        return Category::create([
            'name' => $this->names(),
            'slug' => $this->names('printers'),
            'is_active' => true,
            'requires_subscription' => true,
        ]);
    }

    public function test_moderator_cannot_make_a_category_free_but_can_edit_the_rest(): void
    {
        $category = $this->paidCategory();

        Livewire::actingAs($this->moderator())
            ->test(Show::class, ['category' => $category])
            ->assertDontSee(__('admin.categories.free_toggle'))
            ->set('catIsFree', true)
            ->set('catName', $this->names('Renamed'))
            ->call('saveCategoryDetails')
            ->assertHasNoErrors()
            ->assertSet('catIsFree', false);

        $category->refresh();
        $this->assertTrue($category->requires_subscription);
        $this->assertSame('Renamed', $category->name['en']);
        $this->assertSame(0, CategorySubscriptionAudit::count());
    }

    public function test_moderator_cannot_make_a_free_category_paid(): void
    {
        $category = $this->paidCategory();
        $category->update(['requires_subscription' => false]);

        Livewire::actingAs($this->moderator())
            ->test(Show::class, ['category' => $category])
            ->set('catIsFree', false)
            ->call('saveCategoryDetails')
            ->assertHasNoErrors();

        $this->assertFalse($category->fresh()->requires_subscription);
    }

    public function test_moderator_created_category_is_always_paid(): void
    {
        Livewire::actingAs($this->moderator())
            ->test(Index::class)
            ->set('name', $this->names('New'))
            ->set('isFree', true)
            ->call('createCategory')
            ->assertHasNoErrors();

        $this->assertTrue(Category::sole()->requires_subscription);
        $this->assertSame(0, CategorySubscriptionAudit::count());
    }

    public function test_super_admin_can_change_the_flag_and_it_is_audited(): void
    {
        $category = $this->paidCategory();
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(Show::class, ['category' => $category])
            ->assertSee(__('admin.categories.free_toggle'))
            ->set('catIsFree', true)
            ->call('saveCategoryDetails')
            ->assertHasNoErrors();

        $this->assertFalse($category->fresh()->requires_subscription);

        $audit = CategorySubscriptionAudit::sole();
        $this->assertSame($category->id, $audit->category_id);
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertTrue($audit->old_requires_subscription);
        $this->assertFalse($audit->new_requires_subscription);
        $this->assertNotNull($audit->created_at);
    }

    public function test_saving_without_changing_the_flag_writes_no_audit(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Show::class, ['category' => $this->paidCategory()])
            ->call('saveCategoryDetails')
            ->assertHasNoErrors();

        $this->assertSame(0, CategorySubscriptionAudit::count());
    }

    public function test_super_admin_can_create_a_free_category_and_it_is_audited(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(Index::class)
            ->set('name', $this->names('New'))
            ->set('isFree', true)
            ->call('createCategory')
            ->assertHasNoErrors();

        $this->assertFalse(Category::sole()->requires_subscription);
        $this->assertFalse(CategorySubscriptionAudit::sole()->new_requires_subscription);
    }
}
