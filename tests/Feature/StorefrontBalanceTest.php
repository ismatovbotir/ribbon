<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_contact_page_has_two_equal_columns_and_a_real_breadcrumb(): void
    {
        $this->get('/contact')->assertOk()
            ->assertSee('grid gap-8 lg:grid-cols-2 lg:items-start', false)
            ->assertSee('<nav aria-label="'.e(__('storefront.catalog.breadcrumb_label')).'"', false);
    }

    public function test_home_centres_its_intro_and_gives_how_it_works_three_equal_columns(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('mx-auto max-w-4xl text-center', false)
            ->assertSee('grid gap-4 md:grid-cols-3', false)
            ->assertSee('href="#home-categories-heading"', false)
            ->assertSee('id="home-categories-heading"', false)
            ->assertSee('id="home-how-heading"', false);
    }

    public function test_category_tiles_use_the_balanced_grid_and_a_worded_count(): void
    {
        $text = fn (string $value) => array_fill_keys(config('ribbon.locales'), $value);

        Category::create([
            'name' => $text('Printers'),
            'slug' => $text('printers'),
            'is_active' => true,
            'requires_subscription' => true,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('grid-balanced', false)
            ->assertSee(trans_choice('storefront.home.tile_count', 0, ['count' => 0]));
    }
}
