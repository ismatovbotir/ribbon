<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontNavbarTest extends TestCase
{
    use RefreshDatabase;

    public function test_icon_only_links_keep_their_names_for_screen_readers_and_crawlers(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<span class="sr-only">'.__('storefront.nav.home').'</span>', false)
            ->assertSee('<span class="sr-only">'.__('storefront.nav.contact').'</span>', false)
            ->assertSee('<span class="sr-only">'.e(__('storefront.nav.selection_label')).'</span>', false)
            ->assertSee('title="'.__('storefront.nav.selection_label').'"', false);
    }

    public function test_the_compact_language_menu_links_to_every_language(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="navbar-language"', $html);

        foreach (config('ribbon.locales') as $locale) {
            $this->assertStringContainsString('lang='.$locale, $html);
        }
    }
}
