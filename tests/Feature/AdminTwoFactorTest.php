<?php

namespace Tests\Feature;

use App\Livewire\Admin\Brands\Index as BrandsIndex;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Auth\TwoFactorSetup;
use App\Models\Brand;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserTwoFactor;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ribbon.admin_2fa_required' => true]);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /** @return array<int, string> Recovery codes. */
    private function enrol(): array
    {
        $service = app(TwoFactorService::class);
        $pending = $service->pendingFor($this->admin);
        $codes = $service->confirm($this->admin, $this->otp($pending->secret));

        // Free the current step, so tests can use a fresh code for the login.
        $pending->refresh()->update(['last_used_timestep' => null]);

        return $codes;
    }

    private function secret(): string
    {
        return UserTwoFactor::where('user_id', $this->admin->id)->sole()->secret;
    }

    // ---- Being blocked ----

    public function test_a_password_only_session_is_sent_to_setup_then_challenge(): void
    {
        $this->actingAs($this->admin)->get('/admin/categories')->assertRedirect(route('two-factor.setup'));

        $this->enrol();

        $this->actingAs($this->admin)->get('/admin/categories')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_livewire_actions_are_blocked_without_passing_2fa(): void
    {
        $brand = Brand::create(['name' => 'Zebra']);

        // Route middleware doesn't run on Livewire updates: this is the gate's job.
        Livewire::actingAs($this->admin)->test(BrandsIndex::class)->assertForbidden();

        $this->assertNotNull($brand->fresh());
    }

    public function test_every_admin_gate_is_closed_until_2fa_passes(): void
    {
        $this->actingAs($this->admin);

        foreach ([['admin'], ['super-admin'], ['manage-subscriptions'], ['admin-permission', 'catalog.manage']] as $check) {
            $this->assertFalse(Gate::allows($check[0], array_slice($check, 1)), $check[0]);
        }

        app(TwoFactorService::class)->markPassed($this->admin);

        $this->assertTrue(Gate::allows('super-admin'));
    }

    public function test_passing_for_one_user_does_not_carry_to_another(): void
    {
        app(TwoFactorService::class)->markPassed($this->admin);

        $other = User::factory()->create();
        $other->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        $this->assertTrue(app(TwoFactorService::class)->blocks($other));
    }

    public function test_sellers_and_buyers_are_not_affected(): void
    {
        $this->assertFalse(app(TwoFactorService::class)->blocks(User::factory()->create()));

        $this->get('/')->assertOk();
    }

    public function test_non_admins_cannot_open_the_two_factor_pages(): void
    {
        $this->get('/two-factor/setup')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/two-factor/setup')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/two-factor/challenge')->assertForbidden();
    }

    // ---- Setup ----

    public function test_setup_shows_a_qr_and_confirming_returns_recovery_codes_once(): void
    {
        $component = Livewire::actingAs($this->admin)->test(TwoFactorSetup::class)->assertSeeHtml('<svg');

        $component->set('code', $this->otp($this->secret()))->call('confirm')->assertHasNoErrors();

        $this->assertCount(8, $component->get('recoveryCodes'));
        $this->assertNotNull(UserTwoFactor::sole()->confirmed_at);
        $this->assertFalse(app(TwoFactorService::class)->blocks($this->admin));

        // A later visit never shows them again.
        Livewire::actingAs($this->admin)->test(TwoFactorSetup::class)->assertRedirect(route('admin.categories.index'));
    }

    public function test_setup_rejects_a_wrong_code(): void
    {
        Livewire::actingAs($this->admin)->test(TwoFactorSetup::class)
            ->set('code', '000000')
            ->call('confirm')
            ->assertHasErrors(['code']);

        $this->assertNull(UserTwoFactor::sole()->confirmed_at);
    }

    public function test_setup_cannot_replace_a_confirmed_device(): void
    {
        $this->enrol();

        Livewire::actingAs($this->admin)->test(TwoFactorSetup::class)->assertRedirect(route('two-factor.challenge'));

        $this->assertNull(app(TwoFactorService::class)->confirm($this->admin, $this->otp($this->secret())));
    }

    public function test_secrets_and_recovery_codes_are_encrypted_at_rest(): void
    {
        $codes = $this->enrol();
        $row = DB::table('user_two_factors')->first();

        $this->assertStringNotContainsString($this->secret(), $row->secret);
        $this->assertStringNotContainsString($codes[0], $row->recovery_codes);
        $this->assertNotContains($codes[0], UserTwoFactor::sole()->recovery_codes);
    }

    // ---- Challenge ----

    public function test_a_valid_code_lets_the_admin_in(): void
    {
        $this->enrol();

        Livewire::actingAs($this->admin)->test(TwoFactorChallenge::class)
            ->set('code', $this->otp($this->secret()))
            ->call('verify')
            ->assertRedirect(route('admin.categories.index'));

        $this->assertFalse(app(TwoFactorService::class)->blocks($this->admin));
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $this->enrol();
        $code = $this->otp($this->secret());
        $service = app(TwoFactorService::class);

        $this->assertTrue($service->verify($this->admin, $code));
        $this->assertFalse($service->verify($this->admin, $code));
    }

    public function test_wrong_codes_are_rate_limited_per_user(): void
    {
        $this->enrol();
        $component = Livewire::actingAs($this->admin)->test(TwoFactorChallenge::class);

        foreach (range(1, 5) as $i) {
            $component->set('code', '000000')->call('verify')->assertHasErrors(['code']);
        }

        // Even the right code is refused during the lockout.
        $component->set('code', $this->otp($this->secret()))->call('verify')->assertHasErrors(['code']);

        $this->assertTrue(app(TwoFactorService::class)->blocks($this->admin));
    }

    public function test_a_recovery_code_works_once(): void
    {
        $codes = $this->enrol();
        $service = app(TwoFactorService::class);

        $this->assertTrue($service->verify($this->admin, strtolower($codes[0])));
        $this->assertFalse($service->verify($this->admin, $codes[0]));
        $this->assertTrue($service->verify($this->admin, $codes[1]));
    }

    public function test_an_unconfirmed_setup_cannot_pass_the_challenge(): void
    {
        $pending = app(TwoFactorService::class)->pendingFor($this->admin);

        $this->assertFalse(app(TwoFactorService::class)->verify($this->admin, $this->otp($pending->secret)));
    }

    public function test_reset_command_removes_the_setup_for_re_enrolment(): void
    {
        $this->enrol();

        $this->artisan('two-factor:reset', ['email' => $this->admin->email])
            ->expectsConfirmation("Remove two-factor for {$this->admin->email}? Anyone with their password can then enrol a new device.", 'yes')
            ->assertSuccessful();

        $this->assertSame(0, UserTwoFactor::count());
    }

    // ---- Security headers ----

    public function test_pages_carry_a_policy_without_inline_scripts(): void
    {
        $csp = $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        // Same-origin frames stay allowed: the QA harness depends on them.
        $this->assertStringContainsString("frame-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_inline_scripts_carry_the_requests_nonce(): void
    {
        Setting::current()->update(['google_analytics_id' => 'G-TEST', 'yandex_metrica_id' => '123']);

        $response = $this->get('/')->assertOk();
        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $match);

        preg_match_all('/<script(?![^>]*\bsrc=)(?![^>]*application\/ld\+json)[^>]*>/', $response->getContent(), $scripts);

        $this->assertNotEmpty($scripts[0]);
        foreach ($scripts[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$match[1].'"', $tag);
        }
    }

    public function test_hsts_is_only_sent_over_https_in_production(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }
}
