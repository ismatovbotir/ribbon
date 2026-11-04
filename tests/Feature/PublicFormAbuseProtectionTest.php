<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Storefront\OfferRequest\Show as OfferRequestShow;
use App\Models\OfferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Launch-hardening checks from CLAUDE.md "Security baseline": rate limits
 * and spam traps on the public/unauthenticated forms.
 */
class PublicFormAbuseProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_locks_out_after_five_failed_attempts_even_with_correct_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $component = Livewire::test(Login::class)->set('email', $user->email);

        for ($i = 0; $i < 5; $i++) {
            $component->set('password', 'wrong')->call('login')
                ->assertHasErrors(['email']);
        }

        $component->set('password', 'correct-password')->call('login')
            ->assertHasErrors(['email'])
            ->assertHasNoErrors(['email' => __('auth.login.invalid_credentials')]);

        $this->assertStringContainsString(
            explode(':seconds', trans('auth.login.throttled'))[0],
            $component->errors()->first('email'),
        );

        $this->assertGuest();
    }

    public function test_login_throttle_is_scoped_per_email(): void
    {
        $victim = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->set('email', $other->email)->set('password', 'wrong')->call('login');
        }

        $this->assertFalse(
            RateLimiter::tooManyAttempts('login:'.strtolower($victim->email).'|127.0.0.1', 5)
        );
    }

    public function test_offer_request_honeypot_fakes_success_without_saving(): void
    {
        $this->travel(10)->seconds();

        Livewire::test(OfferRequestShow::class)
            ->set('phone', '+998901234567')
            ->set('website', 'http://spam.example')
            ->tap(fn () => $this->travel(10)->seconds())
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertSame(0, OfferRequest::count());
    }

    public function test_offer_request_submitted_too_fast_is_dropped(): void
    {
        Livewire::test(OfferRequestShow::class)
            ->set('phone', '+998901234567')
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertSame(0, OfferRequest::count());
    }

    public function test_offer_request_is_rate_limited_per_phone_regardless_of_formatting(): void
    {
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit('offer-request:phone:998901234567', 3600);
        }

        $component = Livewire::test(OfferRequestShow::class);
        $this->travel(10)->seconds();

        $component->set('phone', '+998 (90) 123-45-67')
            ->call('submit')
            ->assertHasErrors(['phone'])
            ->assertSet('submitted', false);
    }

    public function test_offer_request_rendered_at_cannot_be_tampered_with(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(OfferRequestShow::class)->set('renderedAt', 0);
    }
}
