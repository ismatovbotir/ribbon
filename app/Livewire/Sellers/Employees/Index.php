<?php

namespace App\Livewire\Sellers\Employees;

use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Owner-only seller team roster: lists every user linked to the
 * authenticated seller via `seller_user` (the Owner plus any Employees),
 * and lets the Owner add/remove Employees. An Employee who navigates here
 * is blocked with a 403 in mount() — see User::isOwnerOf(), the single
 * source of truth this and the "add"/"remove" actions all re-check, since
 * a Livewire component's public methods are independently reachable
 * network requests, not just gated by what buttons happen to render.
 */
class Index extends Component
{
    // ---- Add Employee form ----

    public bool $showAddForm = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    /**
     * Options ordered uz/en/ru — same shared source
     * (config('ribbon.user_locales')) as the registration form's Language
     * field, via <x-language-select>.
     */
    public string $locale = 'uz';

    // ---- Remove confirmation modal ----

    public bool $showRemoveConfirm = false;

    public ?int $removingUserId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isOwnerOf($this->seller()), 403);
    }

    public function seller(): Seller
    {
        return Auth::user()->sellerOrFail();
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('sellers.full_name_label'),
            'email' => __('sellers.email_label'),
            'password' => __('sellers.password_label'),
            'passwordConfirmation' => __('sellers.confirm_password_label'),
            'locale' => __('sellers.language_label'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function addEmployeeMessages(): array
    {
        return [
            'name.required' => __('sellers.validation.full_name_required'),
            'email.required' => __('sellers.validation.email_required'),
            'email.email' => __('sellers.validation.email_invalid'),
            'email.unique' => __('sellers.validation.email_unique'),
            'password.required' => __('sellers.validation.password_required'),
            'password.min' => __('sellers.validation.password_min'),
            'password.same' => __('sellers.validation.password_mismatch'),
            'passwordConfirmation.required' => __('sellers.validation.password_required'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function addEmployeeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            // Same uniqueness rule as seller owner registration — one
            // `users` table shared by owners, employees, and (eventually)
            // admin staff.
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['required', 'string'],
            'locale' => ['required', 'string', Rule::in(config('ribbon.user_locales'))],
        ];
    }

    public function toggleAddForm(): void
    {
        $this->showAddForm = ! $this->showAddForm;

        if (! $this->showAddForm) {
            $this->resetAddForm();
        }
    }

    public function addEmployee(): void
    {
        abort_unless(Auth::user()->isOwnerOf($this->seller()), 403);

        $this->validate($this->addEmployeeRules(), $this->addEmployeeMessages());

        $this->seller()->addEmployee([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'locale' => $this->locale,
        ]);

        $this->resetAddForm();
        $this->showAddForm = false;

        session()->flash('status', __('sellers.employees.added'));
    }

    protected function resetAddForm(): void
    {
        $this->reset(['name', 'email', 'password', 'passwordConfirmation']);
        $this->locale = 'uz';
        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function confirmRemove(int $userId): void
    {
        $this->removingUserId = $userId;
        $this->showRemoveConfirm = true;
    }

    public function cancelRemove(): void
    {
        $this->showRemoveConfirm = false;
        $this->removingUserId = null;
    }

    #[Computed]
    public function removingUser(): ?User
    {
        if (! $this->removingUserId) {
            return null;
        }

        return $this->seller()->users()->find($this->removingUserId);
    }

    /**
     * Detaches the seller_user pivot row only (the User row itself is left
     * intact — simpler and reversible, and there's no other "fully delete
     * a user" cleanup story yet). Never targets the Owner row: only ever
     * removes users carrying the seeded `employee` role, and never the
     * acting user themself, re-checked here even though the UI never
     * offers a Remove action for either case, since this method is an
     * independently reachable request.
     */
    public function removeEmployee(): void
    {
        abort_unless(Auth::user()->isOwnerOf($this->seller()), 403);

        $target = $this->removingUser;

        if (! $target || $target->id === Auth::id()) {
            $this->cancelRemove();

            return;
        }

        $employeeRoleId = Role::where('type', 'seller')->where('slug', 'employee')->value('id');

        if ((int) $target->pivot->role_id !== $employeeRoleId) {
            $this->cancelRemove();

            return;
        }

        $this->seller()->users()->detach($target->id);

        $this->showRemoveConfirm = false;
        $this->removingUserId = null;

        session()->flash('status', __('sellers.employees.removed'));
    }

    public function render()
    {
        $seller = $this->seller();

        $ownerRoleId = Role::where('type', 'seller')->where('slug', 'owner')->value('id');

        // Owner row first, then employees alphabetically by name.
        $members = $seller->users()
            ->orderByRaw('CASE WHEN seller_user.role_id = ? THEN 0 ELSE 1 END', [$ownerRoleId])
            ->orderBy('name')
            ->get();

        return view('livewire.sellers.employees.index', [
            'members' => $members,
            'ownerRoleId' => $ownerRoleId,
        ])->layout('layouts.seller', [
            'title' => __('sellers.nav.employees'),
            'breadcrumb' => [
                ['label' => __('sellers.nav.employees')],
            ],
        ]);
    }
}
