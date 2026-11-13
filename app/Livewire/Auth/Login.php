<?php

namespace App\Livewire\Auth;

use App\Exceptions\AdminAccessDeniedException;
use App\Exceptions\SellerAccessDeniedException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Single, session-based (`web` guard) login shared by admin/CMS staff and
 * sellers — there's no separate guard per actor type, so the actor is
 * resolved from role data *after* authenticating, not from which URL was
 * visited. Admin is checked first: {@see AdminAccessDeniedException} carries
 * no status (role assignment is binary), so it can only mean "not staff,
 * check seller instead." Only if neither an admin role nor an approved
 * Seller link is found does the user get turned away — see login() below.
 */
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'email' => __('auth.email_label'),
            'password' => __('auth.password_label'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.required' => __('auth.validation.email_required'),
            'email.email' => __('auth.validation.email_invalid'),
            'password.required' => __('auth.validation.password_required'),
        ];
    }

    public function login(): void
    {
        $this->validate();

        // Brute-force protection: 5 failed attempts per email+IP, then a
        // lockout. Keyed on both so one attacker IP can't lock a victim out
        // from everywhere, and one email can't be sprayed from one IP.
        $throttleKey = 'login:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('email', __('auth.login.throttled', [
                'seconds' => RateLimiter::availableIn($throttleKey),
            ]));

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', __('auth.login.invalid_credentials'));

            return;
        }

        RateLimiter::clear($throttleKey);

        $this->redirectAuthenticatedUser();
    }

    /**
     * Demo accounts offered as one-click logins in local development.
     * A fixed allowlist of seeded @demo.ribbon.uz users (DemoCatalogSeeder,
     * DemoStaffSeeder), never "any user", so a local DB copied from
     * production can't be entered as a real customer.
     *
     * @var array<string, string> email => lang key
     */
    public const DEV_ACCOUNTS = [
        'admin@demo.ribbon.uz' => 'auth.login.dev_super_admin',
        'moderator@demo.ribbon.uz' => 'auth.login.dev_moderator',
        'seller1@demo.ribbon.uz' => 'auth.login.dev_seller_owner',
        'employee@demo.ribbon.uz' => 'auth.login.dev_seller_employee',
    ];

    public static function devLoginEnabled(): bool
    {
        return app()->isLocal() && config('ribbon.dev_login') === true;
    }

    /**
     * Passwordless login as a demo account. The guard lives here, not only
     * in the view, because Livewire actions are callable by any client.
     * Admin accounts still meet the 2FA challenge (admin.auth middleware).
     */
    public function devLogin(string $email): void
    {
        abort_unless(self::devLoginEnabled(), 404);
        abort_unless(array_key_exists($email, self::DEV_ACCOUNTS), 404);

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->addError('email', __('auth.login.dev_missing', ['email' => $email]));

            return;
        }

        Auth::login($user);

        $this->redirectAuthenticatedUser();
    }

    private function redirectAuthenticatedUser(): void
    {
        $user = Auth::user();

        try {
            $user->adminRoleOrFail();

            session()->regenerate();

            // No dedicated admin dashboard exists yet (see CLAUDE.md — the
            // admin/CMS structure is still being built out); Categories is
            // the first item in the admin sidebar nav, so it's the most
            // sensible landing page today.
            $this->redirectRoute('admin.categories.index', navigate: false);

            return;
        } catch (AdminAccessDeniedException) {
            // Not staff — fall through and check seller access instead.
        }

        try {
            $user->sellerOrFail();
        } catch (SellerAccessDeniedException $e) {
            Auth::logout();

            $this->addError('email', match ($e->status) {
                null => __('auth.login.no_access'),
                'pending' => __('auth.login.pending'),
                default => __('auth.login.inactive'),
            });

            return;
        }

        session()->regenerate();

        $this->redirectRoute('seller.dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login', [
            'devAccounts' => self::devLoginEnabled() ? self::DEV_ACCOUNTS : [],
        ])->layout('layouts.public', [
            'title' => __('auth.login.title'),
        ]);
    }
}
