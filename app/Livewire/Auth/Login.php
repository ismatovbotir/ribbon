<?php

namespace App\Livewire\Auth;

use App\Exceptions\AdminAccessDeniedException;
use App\Exceptions\SellerAccessDeniedException;
use Illuminate\Support\Facades\Auth;
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

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            $this->addError('email', __('auth.login.invalid_credentials'));

            return;
        }

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
        return view('livewire.auth.login')->layout('layouts.public', [
            'title' => __('auth.login.title'),
        ]);
    }
}
