<?php

namespace Tests\Feature;

use App\Livewire\Storefront\Search;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_analytics_page(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());
        Auth::login($admin);

        $this->get(route('admin.analytics.show'))->assertOk();
    }

    public function test_real_search_page_load_records_a_search_query_event(): void
    {
        $this->assertDatabaseCount('search_queries', 0);

        Livewire::test(Search::class, ['query' => 'thermal ribbon']);

        $this->assertDatabaseHas('search_queries', ['query' => 'thermal ribbon']);
    }

    public function test_blank_search_query_is_not_recorded(): void
    {
        Livewire::test(Search::class, ['query' => '']);

        $this->assertDatabaseCount('search_queries', 0);
    }
}
