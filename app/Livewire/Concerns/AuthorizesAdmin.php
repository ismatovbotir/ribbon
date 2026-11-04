<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Gate;

/**
 * In-component authorization for admin Livewire components. Route
 * middleware does not protect `POST /livewire/update`, and public
 * properties / method arguments are client-controlled, so every admin
 * component re-checks access itself.
 *
 * API:
 *  - authorizeAdmin(?string $permission = null): any admin role (gate
 *    `admin`); with a permission slug, additionally requires it (gate
 *    `admin-permission`, Super Admin always passes).
 *  - authorizeSuperAdmin(): gate `super-admin`.
 *
 * Call it in mount() and at the top of every public mutating action.
 * As a safety net the `bootAuthorizesAdmin` lifecycle hook runs on every
 * request (initial render and every update, before any action or property
 * hydration is acted on) and enforces the component's {@see adminAccess()}.
 * Override adminAccess() to return 'super-admin' or a permission slug.
 */
trait AuthorizesAdmin
{
    /**
     * What this component requires: null = any admin role,
     * 'super-admin' = Super Admin only, anything else = permission slug.
     */
    protected function adminAccess(): ?string
    {
        return null;
    }

    public function bootAuthorizesAdmin(): void
    {
        $access = $this->adminAccess();

        if ($access === 'super-admin') {
            $this->authorizeSuperAdmin();
        } else {
            $this->authorizeAdmin($access);
        }
    }

    protected function authorizeAdmin(?string $permission = null): void
    {
        Gate::authorize('admin');

        if ($permission !== null) {
            Gate::authorize('admin-permission', $permission);
        }
    }

    protected function authorizeSuperAdmin(): void
    {
        Gate::authorize('super-admin');
    }
}
