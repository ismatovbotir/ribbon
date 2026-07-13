<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Exceptions\AdminAccessDeniedException;
use App\Exceptions\SellerAccessDeniedException;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The seller companies this user belongs to, with their role (Owner/
     * Employee) within each one carried on the seller_user pivot row.
     */
    public function sellers(): BelongsToMany
    {
        return $this->belongsToMany(Seller::class, 'seller_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /**
     * The admin-type roles assigned to this user via `role_user`. Distinct
     * from `sellers()`'s per-company `seller_user` pivot — admin role
     * assignment isn't scoped to any company, per the `role_user` migration
     * ("Only admin-type roles are assigned here").
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * Resolve the Seller this user is allowed into the seller dashboard
     * for — the single source of truth for "does this user have a usable
     * seller account" shared by the login form and the
     * `EnsureSellerIsAuthenticated` middleware, so the rule (linked to a
     * seller AND that seller is `approved`) only lives in one place. A
     * user belongs to at most one seller in practice today, so the first
     * relation row is authoritative.
     *
     * @throws SellerAccessDeniedException if the user has no linked
     *                                     seller, or their seller isn't `approved` — the exception carries
     *                                     the seller's status (or null) so callers can render a specific
     *                                     message without a second query.
     */
    public function sellerOrFail(): Seller
    {
        $seller = $this->sellers()->first();

        if (! $seller) {
            throw new SellerAccessDeniedException;
        }

        if ($seller->status !== 'approved') {
            throw new SellerAccessDeniedException($seller->status);
        }

        return $seller;
    }

    /**
     * Resolve this user's admin role — the single source of truth for
     * "does this user have a usable admin/CMS login", shared by the login
     * form and the `EnsureAdminIsAuthenticated` middleware, mirroring
     * {@see sellerOrFail()}'s role for the seller side. A user is expected
     * to hold at most one admin role in practice, so the first is
     * authoritative.
     *
     * @throws AdminAccessDeniedException if the user has no admin role
     *                                     assigned via `role_user`.
     */
    public function adminRoleOrFail(): Role
    {
        $role = $this->roles()->first();

        if (! $role) {
            throw new AdminAccessDeniedException;
        }

        return $role;
    }

    /**
     * Whether this user holds an admin role flagged `is_super_admin` —
     * stronger than `adminRoleOrFail()` succeeding, which only means "has
     * some admin role." Used to gate admin sections meant for Super Admin
     * specifically (e.g. Commercial Offers, which surfaces buyers' raw
     * contact details) even after other, lesser-privileged admin roles
     * exist.
     */
    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('is_super_admin', true)->exists();
    }

    /**
     * Whether this user holds the seeded `owner` role on the given
     * $seller's `seller_user` pivot row — the single source of truth for
     * "is this user allowed to manage this seller's team", used to gate
     * `/seller/employees` (only the Owner may view/use it) and to guard
     * against removing/demoting the Owner. Returns false (not an
     * exception) for a user with no relationship to $seller at all, so
     * callers can use it as a plain boolean check.
     */
    public function isOwnerOf(Seller $seller): bool
    {
        $ownerRoleId = Role::where('type', 'seller')->where('slug', 'owner')->value('id');

        return $this->sellers()
            ->wherePivot('seller_id', $seller->id)
            ->wherePivot('role_id', $ownerRoleId)
            ->exists();
    }
}
