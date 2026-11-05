<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/** The code step of an admin sign-in, after the password. */
class TwoFactorChallenge extends Component
{
    public string $code = '';

    public function mount(TwoFactorService $twoFactor): void
    {
        $user = $this->admin();

        if (! $twoFactor->isConfirmed($user)) {
            $this->redirectRoute('two-factor.setup', navigate: false);
        } elseif (! $twoFactor->blocks($user)) {
            $this->redirectRoute('admin.categories.index', navigate: false);
        }
    }

    public function verify(TwoFactorService $twoFactor): void
    {
        $user = $this->admin();

        // Per user, not per IP: a stolen password must not buy unlimited guesses.
        $key = 'two-factor:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', __('auth.two_factor.throttled', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        if (! $twoFactor->verify($user, $this->code)) {
            RateLimiter::hit($key, 60);
            $this->code = '';
            $this->addError('code', __('auth.two_factor.invalid_code'));

            return;
        }

        RateLimiter::clear($key);
        $twoFactor->markPassed($user);

        $this->redirectRoute('admin.categories.index', navigate: false);
    }

    private function admin(): User
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin(), 403);

        return $user;
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge')
            ->layout('layouts.public', ['title' => __('auth.two_factor.challenge_title')]);
    }
}
