<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** First-time 2FA enrolment for admin staff, reached right after the password. */
class TwoFactorSetup extends Component
{
    public string $code = '';

    /** @var array<int, string> Shown once, right after confirming. */
    #[Locked]
    public array $recoveryCodes = [];

    public function mount(TwoFactorService $twoFactor): void
    {
        $user = $this->admin();

        if ($twoFactor->isConfirmed($user)) {
            $this->redirectRoute($twoFactor->blocks($user) ? 'two-factor.challenge' : 'admin.categories.index', navigate: false);
        }
    }

    public function confirm(TwoFactorService $twoFactor): void
    {
        $user = $this->admin();
        $key = 'two-factor-setup:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', __('auth.two_factor.throttled', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        $codes = $twoFactor->confirm($user, $this->code);

        if ($codes === null) {
            RateLimiter::hit($key, 60);
            $this->addError('code', __('auth.two_factor.invalid_code'));

            return;
        }

        RateLimiter::clear($key);
        $twoFactor->markPassed($user);

        $this->recoveryCodes = $codes;
        $this->code = '';
    }

    public function finish(): void
    {
        $this->admin();

        $this->redirectRoute('admin.categories.index', navigate: false);
    }

    private function admin(): User
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin(), 403);

        return $user;
    }

    public function render(TwoFactorService $twoFactor)
    {
        $user = $this->admin();
        $pending = $this->recoveryCodes === [] && ! $twoFactor->isConfirmed($user) ? $twoFactor->pendingFor($user) : null;

        return view('livewire.auth.two-factor-setup', [
            'secret' => $pending?->secret,
            'qrSvg' => $pending ? $twoFactor->qrSvg($user, $pending) : null,
        ])->layout('layouts.public', ['title' => __('auth.two_factor.setup_title')]);
    }
}
