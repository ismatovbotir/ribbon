<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DevLoginTest extends TestCase
{
    use RefreshDatabase;

    private function enable(string $env = 'local', bool $flag = true): void
    {
        $this->app['env'] = $env;
        config(['ribbon.dev_login' => $flag]);
    }

    public function test_buttons_hidden_by_default(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('devLogin');
    }

    public function test_buttons_shown_when_local_and_flag_on(): void
    {
        $this->enable();

        Livewire::test(Login::class)->assertSee(__('auth.login.dev_heading'));
    }

    public function test_flag_alone_does_not_work_outside_local(): void
    {
        $this->enable('production');
        User::factory()->create(['email' => 'seller1@demo.ribbon.uz']);

        Livewire::test(Login::class)->call('devLogin', 'seller1@demo.ribbon.uz')->assertStatus(404);
        $this->assertGuest();
    }

    public function test_local_env_alone_does_not_work_without_flag(): void
    {
        $this->enable('local', false);
        User::factory()->create(['email' => 'seller1@demo.ribbon.uz']);

        Livewire::test(Login::class)->call('devLogin', 'seller1@demo.ribbon.uz')->assertStatus(404);
        $this->assertGuest();
    }

    public function test_email_outside_allowlist_is_refused(): void
    {
        $this->enable();
        User::factory()->create(['email' => 'real.customer@example.com']);

        Livewire::test(Login::class)->call('devLogin', 'real.customer@example.com')->assertStatus(404);
        $this->assertGuest();
    }

    public function test_missing_demo_account_shows_error(): void
    {
        $this->enable();

        Livewire::test(Login::class)->call('devLogin', 'seller1@demo.ribbon.uz')->assertHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_user_without_access_is_logged_out_again(): void
    {
        $this->enable();
        User::factory()->create(['email' => 'seller1@demo.ribbon.uz']);

        Livewire::test(Login::class)->call('devLogin', 'seller1@demo.ribbon.uz')->assertHasErrors(['email']);
        $this->assertGuest();
    }
}
